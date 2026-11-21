<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagos mensuales de contrato:
 * - El mes inicia el día en que empezó el contrato.
 * - Un mes pagado no se puede volver a pagar.
 * - Se permiten cuotas y se muestra cuánto falta del mes.
 */
class ContratoPagoMensualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Contrato $contrato;

    private string $inicioMes;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);

        $miembro = Miembro::create([
            'nombre' => 'Empleado de Prueba',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        // El contrato inicia el día 10 del mes actual: su mes va del 10 al 9.
        $this->inicioMes = now()->startOfMonth()->addDays(9)->toDateString();

        $this->contrato = Contrato::create([
            'id_miembro' => $miembro->id,
            'tipo_compensacion' => 'Salario',
            'salario' => 1000,
            'fecha_inicio' => $this->inicioMes,
            'activo' => true,
        ]);

        Ingreso::create([
            'monto_total' => 5000,
            'fecha' => now()->toDateString(),
            'tipo' => 'Ofrenda',
            'metodo_pago' => 'Efectivo',
        ]);
    }

    private function pagar(float $monto): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('egresos.pagar-contrato.store'), [
            'id_contrato' => $this->contrato->id,
            'periodo_inicio' => $this->inicioMes,
            'monto' => $monto,
        ]);
    }

    private function contratoCumplido(): Contrato
    {
        return Contrato::create([
            'id_miembro' => $this->contrato->id_miembro,
            'tipo_compensacion' => 'Salario',
            'salario' => 500,
            'fecha_inicio' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(9)->toDateString(),
            'fecha_fin' => now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->subDay()->toDateString(),
            'activo' => true,
        ]);
    }

    public function test_un_contrato_cumplido_y_totalmente_pagado_ya_no_se_ofrece_para_pagar(): void
    {
        $contrato = $this->contratoCumplido();
        $inicioPeriodo = $contrato->periodoDelDia()['inicio'];

        $this->actingAs($this->admin)->post(route('egresos.pagar-contrato.store'), [
            'id_contrato' => $contrato->id,
            'periodo_inicio' => $inicioPeriodo->toDateString(),
            'monto' => 500,
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('egresos.pagar-contrato'))
            ->assertOk()
            ->assertViewHas('periodosPorContrato', fn ($periodos) => ! isset($periodos[$contrato->id]));
    }

    public function test_un_contrato_cumplido_con_meses_pendientes_sigue_disponible_para_pagar(): void
    {
        $contrato = $this->contratoCumplido();
        $inicioPeriodo = $contrato->periodoDelDia()['inicio'];

        $this->actingAs($this->admin)->post(route('egresos.pagar-contrato.store'), [
            'id_contrato' => $contrato->id,
            'periodo_inicio' => $inicioPeriodo->toDateString(),
            'monto' => 200,
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('egresos.pagar-contrato'))
            ->assertOk()
            ->assertViewHas('periodosPorContrato', function ($periodos) use ($contrato) {
                return isset($periodos[$contrato->id]) && $periodos[$contrato->id]->isNotEmpty();
            });
    }

    public function test_el_mes_inicia_el_dia_de_inicio_del_contrato(): void
    {
        $periodo = $this->contrato->periodoDelDia();

        $this->assertNotNull($periodo);
        $this->assertSame($this->inicioMes, $periodo['inicio']->toDateString());
        $this->assertSame(
            now()->startOfMonth()->addDays(9)->addMonthNoOverflow()->subDay()->toDateString(),
            $periodo['fin']->toDateString()
        );
        $this->assertSame(1000.0, $periodo['restante']);
    }

    public function test_un_pago_parcial_deja_el_saldo_del_mes_y_se_muestra_en_contratos(): void
    {
        $this->pagar(400)->assertRedirect(route('egresos.index'))->assertSessionHas('success');

        $egreso = Egreso::where('id_contrato', $this->contrato->id)->firstOrFail();
        $this->assertSame($this->inicioMes, $egreso->periodo_inicio->toDateString());

        $periodo = $this->contrato->fresh()->periodoDelDia();
        $this->assertSame(600.0, $periodo['restante']);

        $this->actingAs($this->admin)
            ->get(route('contratos.index'))
            ->assertOk()
            ->assertSee('Falta Bs 600.00');
    }

    public function test_completar_el_mes_marca_el_mes_como_pagado(): void
    {
        $this->pagar(1000)->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('contratos.index'))
            ->assertOk()
            ->assertSee('Mes pagado');

        $periodo = $this->contrato->fresh()->periodoDelDia();
        $this->assertTrue($periodo['completo']);
        $this->assertSame(0.0, $periodo['restante']);
    }

    public function test_no_se_puede_repetir_el_pago_de_un_mes_completo(): void
    {
        $this->pagar(1000)->assertSessionHas('success');

        $this->pagar(100)
            ->assertSessionHasErrors('periodo_inicio');

        $this->assertSame(1, Egreso::where('id_contrato', $this->contrato->id)->count());
    }

    public function test_no_se_puede_pagar_mas_de_lo_que_falta_del_mes(): void
    {
        $this->pagar(1200)
            ->assertSessionHasErrors('monto');

        $this->assertSame(0, Egreso::where('id_contrato', $this->contrato->id)->count());
    }

    public function test_el_mes_pagado_no_se_ofrece_en_el_selector_de_pago(): void
    {
        $this->pagar(1000)->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('egresos.pagar-contrato'))
            ->assertOk()
            ->assertViewHas('periodosPorContrato', function ($periodos) {
                return $periodos[$this->contrato->id]->isEmpty();
            });
    }
}
