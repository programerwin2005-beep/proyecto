<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caja blanca exclusiva del método EgresoController::create().
 *
 * Instrucciones del método:
 *   C1  $contratos = Contrato::with('miembro')->get();
 *   C2  $lideres = LiderIglesia::with('cargo')->get()->sortBy('nombre')->values();
 *   C3  $saldoDisponible = $this->saldoDisponible();
 *   C4  return view('egresos.create', ...);
 */
class EgresoCreateCajaBlancaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cargo $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'nombre' => 'Administrador',
            'estado' => 'Activo',
            'nivel_acceso' => 1,
        ]);

        $this->admin = User::factory()->create(['email' => 'admin_create_egresos@proyecto.com']);
        $this->admin->roles()->attach($role->id);

        $this->cargo = Cargo::create(['nombre' => 'Pastor', 'nivel_jerarquico' => 1]);
    }

    private function ingreso(float $monto): Ingreso
    {
        return Ingreso::create([
            'monto_total' => $monto,
            'fecha' => now()->toDateString(),
            'tipo' => 'Ofrenda',
            'metodo_pago' => 'Efectivo',
        ]);
    }

    private function egreso(float $monto): Egreso
    {
        return Egreso::create([
            'tipo_egreso' => 'Mantenimiento',
            'monto' => $monto,
            'fecha' => now()->toDateString(),
            'responsable' => 'Pastor Juan',
        ]);
    }

    private function lider(string $nombre): LiderIglesia
    {
        return LiderIglesia::create([
            'id_cargo' => $this->cargo->id,
            'nombre' => $nombre,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
        ]);
    }

    private function contrato(Miembro $miembro): Contrato
    {
        return Contrato::create([
            'id_miembro' => $miembro->id,
            'tipo_compensacion' => 'Salario',
            'salario' => 500,
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);
    }

    private function miembro(string $nombre): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    // CB-EC01: camino principal con datos (C1, C2, C3, C4)
    public function test_cb_ec01_create_con_datos(): void
    {
        $this->ingreso(1000);
        $this->egreso(300);
        $this->contrato($this->miembro('María Gómez'));
        $this->lider('Zacarías Pérez');
        $this->lider('Ana Torres');

        $this->actingAs($this->admin)
            ->get(route('egresos.create'))
            ->assertOk()
            ->assertViewIs('egresos.create')
            ->assertViewHas('saldoDisponible', 700.0)
            ->assertViewHas('contratos', fn ($contratos) => $contratos->count() === 1)
            ->assertViewHas('lideres', fn ($lideres) => $lideres->pluck('nombre')->all() === ['Ana Torres', 'Zacarías Pérez'])
            ->assertSee('Bs 700.00')
            ->assertSee('María Gómez');
    }

    // CB-EC02: sin datos, el saldo es cero y las colecciones están vacías
    public function test_cb_ec02_create_sin_datos(): void
    {
        $this->actingAs($this->admin)
            ->get(route('egresos.create'))
            ->assertOk()
            ->assertViewIs('egresos.create')
            ->assertViewHas('saldoDisponible', 0.0)
            ->assertViewHas('contratos', fn ($contratos) => $contratos->isEmpty())
            ->assertViewHas('lideres', fn ($lideres) => $lideres->isEmpty())
            ->assertSee('Bs 0.00');
    }

    // CB-EC03: los registros eliminados lógicamente no se listan (C1, C2)
    public function test_cb_ec03_create_excluye_eliminados(): void
    {
        $contrato = $this->contrato($this->miembro('Miembro Eliminado'));
        $lider = $this->lider('Líder Eliminado');

        $contrato->delete();
        $lider->delete();

        $this->actingAs($this->admin)
            ->get(route('egresos.create'))
            ->assertOk()
            ->assertViewHas('contratos', fn ($contratos) => $contratos->isEmpty())
            ->assertViewHas('lideres', fn ($lideres) => $lideres->isEmpty());
    }

    // CB-EC04: el saldo se calcula con varios ingresos y egresos (C3 -> saldoDisponible)
    public function test_cb_ec04_create_calcula_saldo_con_multiples_movimientos(): void
    {
        $this->ingreso(1500);
        $this->ingreso(500);
        $this->egreso(200);
        $this->egreso(300);

        $this->actingAs($this->admin)
            ->get(route('egresos.create'))
            ->assertOk()
            ->assertViewHas('saldoDisponible', 1500.0)
            ->assertSee('Bs 1,500.00');
    }
}
