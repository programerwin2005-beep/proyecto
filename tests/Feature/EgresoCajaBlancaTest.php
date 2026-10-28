<?php

namespace Tests\Feature;

use App\Http\Controllers\EgresoController;
use App\Models\Cargo;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de caja blanca (cobertura de instrucciones y decisiones)
 * sobre App\Http\Controllers\EgresoController.
 *
 * CB-E01 index()            | CB-E05 edit()
 * CB-E02 create()           | CB-E06 update()
 * CB-E03 store()            | CB-E07 destroy()
 * CB-E04 show()             | CB-E08 saldoDisponible()
 */
class EgresoCajaBlancaTest extends TestCase
{
    use RefreshDatabase;

    private const RESPONSABLE = 'Pastor Juan';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'nombre' => 'Administrador',
            'estado' => 'Activo',
            'nivel_acceso' => 1,
        ]);

        $this->admin = User::factory()->create(['email' => 'admin_egresos@proyecto.com']);
        $this->admin->roles()->attach($role->id);

        $cargo = Cargo::create(['nombre' => 'Pastor', 'nivel_jerarquico' => 1]);

        LiderIglesia::create([
            'id_cargo' => $cargo->id,
            'nombre' => self::RESPONSABLE,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
        ]);
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

    private function datos(array $override = []): array
    {
        return array_merge([
            'id_contrato' => null,
            'tipo_egreso' => 'Mantenimiento',
            'monto' => 300,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Reparación de aire acondicionado',
            'responsable' => self::RESPONSABLE,
            'comprobante' => null,
        ], $override);
    }

    private function egreso(array $override = []): Egreso
    {
        return Egreso::create($this->datos($override));
    }

    // CB-E01: index() con ingresos y egresos
    public function test_cb_e01_index_muestra_totales_y_listado(): void
    {
        $this->ingreso(1000);
        $this->egreso();

        $this->actingAs($this->admin)
            ->get(route('egresos.index'))
            ->assertOk()
            ->assertSee('Bs 300.00')
            ->assertSee('Mantenimiento')
            ->assertSee(self::RESPONSABLE);
    }

    // CB-E02: create() calcula el saldo sin ignorar egresos
    public function test_cb_e02_create_muestra_saldo_disponible(): void
    {
        $this->ingreso(1000);
        $this->egreso();

        $this->actingAs($this->admin)
            ->get(route('egresos.create'))
            ->assertOk()
            ->assertViewHas('saldoDisponible', 700.0);
    }

    // CB-E03: store() con saldo suficiente
    public function test_cb_e03_store_registra_egreso(): void
    {
        $this->ingreso(1000);

        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos())
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', [
            'tipo_egreso' => 'Mantenimiento',
            'monto' => 300,
            'responsable' => self::RESPONSABLE,
        ]);
    }

    // CB-E03b: store() rechaza monto mayor al saldo (decisión D2 = true)
    public function test_cb_e03b_store_rechaza_monto_sin_saldo(): void
    {
        $this->ingreso(1000);

        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos(['monto' => 1500]))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseCount('egresos', 0);
    }

    // CB-E03c: store() con validación fallida (decisión D5 = false)
    public function test_cb_e03c_store_rechaza_datos_invalidos(): void
    {
        $this->ingreso(1000);

        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos([
                'tipo_egreso' => 'Tipo inexistente',
                'responsable' => 'Nadie',
            ]))
            ->assertSessionHasErrors(['tipo_egreso', 'responsable']);

        $this->assertDatabaseCount('egresos', 0);
    }

    // CB-E04: show() con registro activo y con registro eliminado
    public function test_cb_e04_show_muestra_egreso_activo_y_eliminado(): void
    {
        $egreso = $this->egreso();

        $this->actingAs($this->admin)
            ->get(route('egresos.show', $egreso->id))
            ->assertOk()
            ->assertSee('Mantenimiento');

        $egreso->delete();

        $this->actingAs($this->admin)
            ->get(route('egresos.show', $egreso->id))
            ->assertOk()
            ->assertSee('Mantenimiento');
    }

    // CB-E04b: show() con ID inexistente (findOrFail = false)
    public function test_cb_e04b_show_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('egresos.show', 99999))
            ->assertNotFound();
    }

    // CB-E05: edit() ignora el egreso actual al calcular el saldo
    public function test_cb_e05_edit_calcula_saldo_ignorando_el_egreso(): void
    {
        $this->ingreso(1000);
        $egreso = $this->egreso();

        $this->actingAs($this->admin)
            ->get(route('egresos.edit', $egreso->id))
            ->assertOk()
            ->assertViewHas('saldoDisponible', 1000.0);
    }

    // CB-E06: update() con saldo suficiente
    public function test_cb_e06_update_modifica_egreso(): void
    {
        $this->ingreso(1000);
        $egreso = $this->egreso();

        $this->actingAs($this->admin)
            ->put(route('egresos.update', $egreso->id), $this->datos([
                'monto' => 200,
                'descripcion' => 'Monto corregido',
            ]))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', ['id' => $egreso->id, 'monto' => 200]);
    }

    // CB-E06b: update() rechaza monto mayor al saldo disponible
    public function test_cb_e06b_update_rechaza_monto_sin_saldo(): void
    {
        $this->ingreso(1000);
        $egreso = $this->egreso();

        $this->actingAs($this->admin)
            ->put(route('egresos.update', $egreso->id), $this->datos(['monto' => 2000]))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseHas('egresos', ['id' => $egreso->id, 'monto' => 300]);
    }

    // CB-E06c: update() sobre un registro eliminado (withTrashed)
    public function test_cb_e06c_update_permite_modificar_eliminado(): void
    {
        $this->ingreso(1000);
        $egreso = $this->egreso();
        $egreso->delete();

        $this->actingAs($this->admin)
            ->put(route('egresos.update', $egreso->id), $this->datos([
                'monto' => 200,
                'descripcion' => 'Modificado tras borrar',
            ]))
            ->assertRedirect(route('egresos.index'));

        $this->assertDatabaseHas('egresos', ['id' => $egreso->id, 'monto' => 200]);
    }

    // CB-E07: destroy() con registro activo
    public function test_cb_e07_destroy_elimina_logicamente(): void
    {
        $egreso = $this->egreso();

        $this->actingAs($this->admin)
            ->delete(route('egresos.destroy', $egreso->id))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('egresos', ['id' => $egreso->id]);
    }

    // CB-E07b: destroy() sobre un registro ya eliminado (sin withTrashed -> 404)
    public function test_cb_e07b_destroy_eliminado_devuelve_404(): void
    {
        $egreso = $this->egreso();
        $egreso->delete();

        $this->actingAs($this->admin)
            ->delete(route('egresos.destroy', $egreso->id))
            ->assertNotFound();
    }

    // CB-E08: saldoDisponible() en sus tres variantes
    public function test_cb_e08_saldo_disponible_con_y_sin_ignorar_id(): void
    {
        $this->ingreso(1000);
        $egreso = $this->egreso();
        $controller = app(EgresoController::class);

        // D1 = false: sin ignorar ningún egreso
        $this->assertEqualsWithDelta(700.0, $controller->saldoDisponible(), 0.001);

        // D1 = true: se suma de vuelta el egreso indicado
        $this->assertEqualsWithDelta(1000.0, $controller->saldoDisponible($egreso->id), 0.001);

        // D1 = true pero el registro no existe: se aplica la rama "?? 0"
        $this->assertEqualsWithDelta(700.0, $controller->saldoDisponible(99999), 0.001);
    }
}
