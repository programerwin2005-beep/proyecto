<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Ingreso;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El ingreso general ya no se asocia a una actividad; para eso existe el
 * flujo separado "Ingreso Actividad".
 */
class IngresoActividadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Actividad $actividad;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);

        $ministerio = Ministerio::create(['nombre' => 'Eventos']);

        $this->actividad = Actividad::create([
            'id_ministerio' => $ministerio->id,
            'nombre' => 'Concierto Benéfico',
            'fecha' => now()->toDateString(),
            'estado' => 'Programado',
        ]);
    }

    public function test_ingresos_muestra_los_botones_nuevo_ingreso_e_ingreso_actividad(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ingresos.index'))
            ->assertOk()
            ->assertSee('Nuevo Ingreso')
            ->assertSee('Ingreso Actividad')
            ->assertSee(route('ingresos.create'), false)
            ->assertSee(route('ingresos.actividad.create'), false);
    }

    public function test_registrar_ingreso_ya_no_ofrece_asociar_actividad(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ingresos.create'))
            ->assertOk()
            ->assertDontSee('Asociar a Actividad')
            ->assertDontSee('name="id_actividad"', false);
    }

    public function test_formulario_ingreso_actividad_lista_las_actividades(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ingresos.actividad.create'))
            ->assertOk()
            ->assertSee('Registrar Ingreso de Actividad')
            ->assertSee($this->actividad->nombre)
            ->assertDontSee('Asociar a Culto')
            ->assertDontSee('name="id_culto"', false);
    }

    public function test_ingreso_actividad_requiere_una_actividad(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ingresos.actividad.store'), [
                'monto_total' => 100,
                'metodo_pago' => 'Efectivo',
                'tipo' => 'Actividad',
            ])
            ->assertSessionHasErrors('id_actividad');

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_registra_el_ingreso_asociado_a_la_actividad(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ingresos.actividad.store'), [
                'id_actividad' => $this->actividad->id,
                'monto_total' => 250,
                'metodo_pago' => 'Transferencia',
                'tipo' => 'Actividad',
                'fecha' => now()->toDateString(),
            ])
            ->assertRedirect(route('ingresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ingresos', [
            'id_actividad' => $this->actividad->id,
            'id_culto' => null,
            'monto_total' => 250,
            'tipo' => 'Actividad',
            'metodo_pago' => 'Transferencia',
        ]);
    }

    public function test_ingreso_general_se_guarda_sin_actividad(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ingresos.store'), [
                'monto_total' => 80,
                'metodo_pago' => 'Efectivo',
                'tipo' => 'Ofrenda',
                'fecha' => now()->toDateString(),
            ])
            ->assertRedirect(route('ingresos.index'));

        $ingreso = Ingreso::firstOrFail();

        $this->assertNull($ingreso->id_actividad);
        $this->assertSame('Ofrenda', $ingreso->tipo?->value);
    }
}
