<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\LiderIglesia;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinisterioTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $user = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $user->roles()->attach($rol->id);

        return $user;
    }

    private function lider(string $nombre = 'Wilson', string $cargo = 'Pastor'): LiderIglesia
    {
        $cargoModel = Cargo::create(['nombre' => $cargo, 'nivel_jerarquico' => 1]);

        return LiderIglesia::create([
            'id_cargo' => $cargoModel->id,
            'nombre' => $nombre,
            'fecha_inicio' => '2026-01-01',
            'activo' => true,
        ]);
    }

    public function test_listado_muestra_al_lider_y_su_cargo(): void
    {
        $lider = $this->lider();
        Ministerio::create(['nombre' => 'Musical', 'id_lider_iglesia' => $lider->id]);

        $this->actingAs($this->administrador())
            ->get('/ministerios')
            ->assertOk()
            ->assertSee('Musical')
            ->assertSee('Wilson')
            ->assertSee('Pastor');
    }

    public function test_detalle_muestra_el_cargo_del_lider(): void
    {
        $lider = $this->lider();
        $ministerio = Ministerio::create(['nombre' => 'Musical', 'id_lider_iglesia' => $lider->id]);

        $this->actingAs($this->administrador())
            ->get('/ministerios/'.$ministerio->id)
            ->assertOk()
            ->assertSee('Cargo del Líder')
            ->assertSee('Pastor');
    }

    public function test_se_puede_crear_un_ministerio_con_lider(): void
    {
        $lider = $this->lider();

        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'descripcion' => 'Equipo de alabanza',
                'id_lider_iglesia' => $lider->id,
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertDatabaseHas('ministerios', [
            'nombre' => 'Alabanza',
            'id_lider_iglesia' => $lider->id,
        ]);
    }

    public function test_rechaza_un_lider_inexistente(): void
    {
        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => 9999,
            ])
            ->assertSessionHasErrors('id_lider_iglesia');

        $this->assertDatabaseMissing('ministerios', ['nombre' => 'Alabanza']);
    }
}
