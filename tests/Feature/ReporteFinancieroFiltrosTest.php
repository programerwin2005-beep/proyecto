<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteFinancieroFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Role::firstOrCreate(['nombre' => 'Administrador'], ['estado' => 'Activo', 'nivel_acceso' => 1]);
        $user = User::factory()->create();
        $user->roles()->attach($rol);
        $this->actingAs($user);
        Ingreso::create(['tipo' => 'Ofrenda', 'monto_total' => 120, 'fecha' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
        Ingreso::create(['tipo' => 'Diezmo', 'monto_total' => 80, 'fecha' => today()->toDateString(), 'metodo_pago' => 'Transferencia']);
        Egreso::create(['tipo_egreso' => 'Mantenimiento', 'monto' => 30, 'fecha' => today()->toDateString(), 'responsable' => 'Ana Pérez', 'descripcion' => 'Reparación']);
        Egreso::create(['tipo_egreso' => 'Salarios', 'monto' => 50, 'fecha' => today()->toDateString(), 'responsable' => 'Ana Pérez']);
    }

    public function test_filtra_ingresos_por_tipo_y_metodo_y_recalcula_totales(): void
    {
        $this->get(route('reportes.financiero', ['movimiento' => 'ingresos', 'tipo_ingreso' => 'Ofrenda', 'metodo_pago' => 'Efectivo']))
            ->assertOk()->assertViewHas('totalIngresos', 120)
            ->assertViewHas('totalEgresos', 0)->assertViewHas('balance', 120)
            ->assertViewHas('ingresos', fn ($filas) => $filas->count() === 1)
            ->assertViewHas('egresos', fn ($filas) => $filas->isEmpty());
    }

    public function test_filtra_egresos_y_el_metodo_solo_afecta_ingresos(): void
    {
        $this->get(route('reportes.financiero', ['movimiento' => 'egresos', 'tipo_egreso' => 'Mantenimiento', 'metodo_pago' => 'Tarjeta']))
            ->assertOk()->assertViewHas('totalIngresos', 0)->assertViewHas('totalEgresos', 30)
            ->assertViewHas('balance', -30)->assertSee('Reparación');
        $this->get(route('reportes.financiero', ['metodo_pago' => 'Transferencia']))
            ->assertOk()->assertViewHas('totalIngresos', 80)->assertViewHas('totalEgresos', 80);
    }

    public function test_descripcion_incluye_miembro_aun_si_fue_dado_de_baja(): void
    {
        $miembro = Miembro::create(['nombre' => 'Lucía Pagadora', 'fecha_ingreso' => today()->toDateString(), 'estado' => 'Activo']);
        $curso = Curso::create(['nombre' => 'Curso', 'f_inicio' => today()->toDateString(), 'cupo_max' => 10, 'tiene_pago' => true, 'monto_inscripcion' => 100]);
        $inscripcion = Inscripcion::create(['miembro_id' => $miembro->id, 'curso_id' => $curso->id, 'fecha_inscripcion' => today()->toDateString(), 'estado' => 'Activo']);
        $pago = Pago::registrar($inscripcion, ['monto' => 65, 'fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Tarjeta']);
        // También cubre ingresos antiguos que resuelven estos datos desde el pago.
        $pago->ingreso->update(['monto_total' => null, 'fecha' => null, 'metodo_pago' => null]);
        $miembro->delete();
        $inscripcion->delete();
        $this->get(route('reportes.financiero', ['movimiento' => 'ingresos', 'tipo_ingreso' => 'Inscripción', 'metodo_pago' => 'Tarjeta']))
            ->assertOk()->assertSee('Inscripción · Tarjeta · Lucía Pagadora')
            ->assertViewHas('totalIngresos', 65);
    }

    public function test_valida_fechas_y_opciones_y_aplica_rango(): void
    {
        $this->get(route('reportes.financiero', ['desde' => '2026-12-31', 'hasta' => '2026-01-01']))
            ->assertSessionHasErrors('hasta');
        $this->get(route('reportes.financiero', ['movimiento' => 'invalido']))
            ->assertSessionHasErrors('movimiento');
        $this->get(route('reportes.financiero', ['desde' => today()->subYear()->toDateString(), 'hasta' => today()->subDay()->toDateString()]))
            ->assertOk()->assertViewHas('totalIngresos', 0)->assertViewHas('totalEgresos', 0);
    }
}
