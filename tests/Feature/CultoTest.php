<?php

namespace Tests\Feature;

use App\Models\Culto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Los cultos dejaron de depender de un líder de iglesia.
 */
class CultoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);
    }

    public function test_la_tabla_cultos_ya_no_tiene_id_lider_iglesia(): void
    {
        $this->assertFalse(Schema::hasColumn('cultos', 'id_lider_iglesia'));
    }

    public function test_el_formulario_de_culto_ya_no_pide_lider(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cultos.create'))
            ->assertOk()
            ->assertDontSee('Líder a Cargo')
            ->assertDontSee('name="id_lider_iglesia"', false);
    }

    public function test_registra_un_culto_sin_lider(): void
    {
        $this->actingAs($this->admin)
            ->post(route('cultos.store'), [
                'nombre' => 'Culto Sin Lider',
                'dia_semana' => 'Domingo',
                'hora' => '10:00',
                'descripcion' => 'Culto de prueba',
            ])
            ->assertRedirect(route('cultos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cultos', [
            'nombre' => 'Culto Sin Lider',
            'dia_semana' => 'Domingo',
        ]);
    }

    public function test_edita_un_culto_sin_lider(): void
    {
        $culto = Culto::create([
            'nombre' => 'Culto Original',
            'dia_semana' => 'Lunes',
            'hora' => '18:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('cultos.edit', $culto))
            ->assertOk()
            ->assertDontSee('Líder a Cargo');

        $this->actingAs($this->admin)
            ->put(route('cultos.update', $culto), [
                'nombre' => 'Culto Actualizado',
                'dia_semana' => 'Martes',
                'hora' => '19:00',
                'descripcion' => 'Actualizado',
            ])
            ->assertRedirect(route('cultos.index'));

        $this->assertDatabaseHas('cultos', [
            'id' => $culto->id,
            'nombre' => 'Culto Actualizado',
            'dia_semana' => 'Martes',
        ]);
    }

    public function test_la_lista_y_el_detalle_funcionan_sin_lider(): void
    {
        $culto = Culto::create([
            'nombre' => 'Culto Visible',
            'dia_semana' => 'Viernes',
            'hora' => '20:00',
            'descripcion' => 'Descripcion visible',
        ]);

        $this->actingAs($this->admin)
            ->get(route('cultos.index'))
            ->assertOk()
            ->assertSee('Culto Visible')
            ->assertDontSee('Líder a Cargo');

        $this->actingAs($this->admin)
            ->get(route('cultos.show', $culto))
            ->assertOk()
            ->assertSee('Culto Visible')
            ->assertDontSee('Líder Encargado');
    }
}
