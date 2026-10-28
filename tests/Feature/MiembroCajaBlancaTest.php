<?php

namespace Tests\Feature;

use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de caja blanca (cobertura de instrucciones y decisiones)
 * sobre App\Http\Controllers\MiembroController.
 *
 * CB-M01 index()   | CB-M05 edit()
 * CB-M02 create()  | CB-M06 update()
 * CB-M03 store()   | CB-M07 destroy()
 * CB-M04 show()
 */
class MiembroCajaBlancaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'nombre' => 'Administrador',
            'estado' => 'Activo',
            'nivel_acceso' => 1,
        ]);

        $this->admin = User::factory()->create(['email' => 'admin_miembros@proyecto.com']);
        $this->admin->roles()->attach($role->id);
    }

    private function datos(array $override = []): array
    {
        return array_merge([
            'nombre' => 'Juan Pérez',
            'email' => 'juan@proyecto.com',
            'telefono' => '88888888',
            'direccion' => 'Calle 1',
            'f_nacimiento' => now()->subYears(20)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
            'sexo' => 'Masculino',
            'estado' => 'Activo',
        ], $override);
    }

    private function miembro(array $override = []): Miembro
    {
        return Miembro::create($this->datos($override));
    }

    // CB-M01: index()
    public function test_cb_m01_index_lista_miembros(): void
    {
        $this->miembro();

        $this->actingAs($this->admin)
            ->get(route('miembros.index'))
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    // CB-M02: create()
    public function test_cb_m02_create_muestra_formulario(): void
    {
        $this->actingAs($this->admin)
            ->get(route('miembros.create'))
            ->assertOk();
    }

    // CB-M03: store() con datos válidos
    public function test_cb_m03_store_crea_miembro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('miembros.store'), $this->datos())
            ->assertRedirect(route('miembros.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('miembros', ['nombre' => 'Juan Pérez']);
    }

    // CB-M03b: store() con datos inválidos (decisión de validación = false)
    public function test_cb_m03b_store_rechaza_datos_invalidos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('miembros.store'), $this->datos([
                'nombre' => '',
                'fecha_ingreso' => '',
            ]))
            ->assertSessionHasErrors(['nombre', 'fecha_ingreso']);

        $this->assertDatabaseCount('miembros', 0);
    }

    // CB-M04: show() con registro activo
    public function test_cb_m04_show_muestra_miembro(): void
    {
        $miembro = $this->miembro();

        $this->actingAs($this->admin)
            ->get(route('miembros.show', $miembro->id))
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    // CB-M04b: show() con registro eliminado (withTrashed)
    public function test_cb_m04b_show_encuentra_miembro_eliminado(): void
    {
        $miembro = $this->miembro();
        $miembro->delete();

        $this->actingAs($this->admin)
            ->get(route('miembros.show', $miembro->id))
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    // CB-M04c: show() con ID inexistente (findOrFail lanza 404)
    public function test_cb_m04c_show_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('miembros.show', 99999))
            ->assertNotFound();
    }

    // CB-M05: edit()
    public function test_cb_m05_edit_muestra_formulario(): void
    {
        $miembro = $this->miembro();

        $this->actingAs($this->admin)
            ->get(route('miembros.edit', $miembro->id))
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    // CB-M06: update() con datos válidos
    public function test_cb_m06_update_modifica_miembro(): void
    {
        $miembro = $this->miembro();

        $this->actingAs($this->admin)
            ->put(route('miembros.update', $miembro->id), $this->datos([
                'nombre' => 'Juan Pérez Actualizado',
            ]))
            ->assertRedirect(route('miembros.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('miembros', ['nombre' => 'Juan Pérez Actualizado']);
    }

    // CB-M06b: update() con nombre duplicado (decisión de validación = false)
    public function test_cb_m06b_update_rechaza_nombre_duplicado(): void
    {
        $this->miembro(['nombre' => 'Ana López', 'email' => 'ana@proyecto.com']);
        $otro = $this->miembro(['nombre' => 'Luis Díaz', 'email' => 'luis@proyecto.com']);

        $this->actingAs($this->admin)
            ->put(route('miembros.update', $otro->id), $this->datos([
                'nombre' => 'Ana López',
                'email' => 'luis@proyecto.com',
            ]))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseHas('miembros', ['id' => $otro->id, 'nombre' => 'Luis Díaz']);
    }

    // CB-M06c: update() sobre un registro eliminado (withTrashed)
    public function test_cb_m06c_update_permite_modificar_eliminado(): void
    {
        $miembro = $this->miembro();
        $miembro->delete();

        $this->actingAs($this->admin)
            ->put(route('miembros.update', $miembro->id), $this->datos([
                'nombre' => 'Modificado tras borrar',
            ]))
            ->assertRedirect(route('miembros.index'));

        $this->assertDatabaseHas('miembros', ['id' => $miembro->id, 'nombre' => 'Modificado tras borrar']);
    }

    // CB-M07: destroy() con registro activo
    public function test_cb_m07_destroy_elimina_logicamente(): void
    {
        $miembro = $this->miembro();

        $this->actingAs($this->admin)
            ->delete(route('miembros.destroy', $miembro->id))
            ->assertRedirect(route('miembros.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('miembros', ['id' => $miembro->id]);
    }

    // CB-M07b: destroy() sobre un registro ya eliminado (sin withTrashed -> 404)
    public function test_cb_m07b_destroy_eliminado_devuelve_404(): void
    {
        $miembro = $this->miembro();
        $miembro->delete();

        $this->actingAs($this->admin)
            ->delete(route('miembros.destroy', $miembro->id))
            ->assertNotFound();
    }
}
