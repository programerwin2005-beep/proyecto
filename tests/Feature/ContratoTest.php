<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Los contratos dejaron de depender de un líder de iglesia.
 */
class ContratoTest extends TestCase
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

    public function test_la_tabla_contratos_ya_no_tiene_id_lider_iglesia(): void
    {
        $this->assertFalse(Schema::hasColumn('contratos', 'id_lider_iglesia'));
    }

    public function test_la_tabla_contratos_ya_no_tiene_id_cargo(): void
    {
        $this->assertFalse(Schema::hasColumn('contratos', 'id_cargo'));
    }

    public function test_el_formulario_de_contrato_ya_no_pide_lider(): void
    {
        $this->actingAs($this->admin)
            ->get(route('contratos.create'))
            ->assertOk()
            ->assertDontSee('Líder de Iglesia')
            ->assertDontSee('name="id_lider_iglesia"', false);
    }

    public function test_registra_un_contrato_sin_lider(): void
    {
        $miembro = Miembro::create([
            'nombre' => 'Trabajador Sin Lider',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->actingAs($this->admin)
            ->post(route('contratos.store'), [
                'id_miembro' => $miembro->id,
                'tipo_compensacion' => 'Salario',
                'salario' => 1000,
                'fecha_inicio' => now()->toDateString(),
            ])
            ->assertRedirect(route('contratos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contratos', [
            'id_miembro' => $miembro->id,
            'tipo_compensacion' => 'Salario',
        ]);
    }

    public function test_la_lista_y_el_detalle_funcionan_sin_lider(): void
    {
        $miembro = Miembro::create([
            'nombre' => 'Miembro Visible',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $contrato = Contrato::create([
            'id_miembro' => $miembro->id,
            'tipo_compensacion' => 'Salario',
            'salario' => 750,
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('contratos.index'))
            ->assertOk()
            ->assertSee('Miembro Visible')
            ->assertDontSee('Líder Asignado');

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $contrato))
            ->assertOk()
            ->assertSee('Miembro Visible')
            ->assertDontSee('Líder Asignado');
    }
}
