<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\LiderIglesia;
use App\Models\Miembro;
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

    public function test_formularios_de_ministerio_listan_los_miembros(): void
    {
        $this->miembro('Ana Torres');
        $ministerio = Ministerio::create(['nombre' => 'Alabanza']);

        $this->actingAs($this->administrador())
            ->get('/ministerios/create')
            ->assertOk()
            ->assertSee('Miembros del Ministerio')
            ->assertSee('Ana Torres');

        $this->actingAs($this->administrador())
            ->get('/ministerios/'.$ministerio->id.'/edit')
            ->assertOk()
            ->assertSee('Miembros del Ministerio')
            ->assertSee('Ana Torres');
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

    public function test_crea_un_ministerio_y_asigna_el_ministerio_a_sus_miembros(): void
    {
        $lider = $this->lider();

        $ana = $this->miembro('Ana Torres');
        $beto = $this->miembro('Beto Ruiz');

        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$ana->id, $beto->id],
            ])
            ->assertRedirect(route('ministerios.index'));

        $ministerio = Ministerio::where('nombre', 'Alabanza')->firstOrFail();

        $this->assertCount(2, $ministerio->fresh()->miembros);
        $this->assertSame($ministerio->id, $ana->fresh()->id_ministerio);
        $this->assertSame($ministerio->id, $beto->fresh()->id_ministerio);

        // El líder dirige el ministerio, pero no se guarda como miembro.
        $this->assertEqualsCanonicalizing(
            [$ana->id, $beto->id],
            Miembro::where('id_ministerio', $ministerio->id)->pluck('id')->all()
        );
    }

    public function test_rechaza_un_miembro_que_ya_pertenece_a_otro_ministerio(): void
    {
        $otro = Ministerio::create(['nombre' => 'Jóvenes']);
        $ana = $this->miembro('Ana Torres');
        $ana->update(['id_ministerio' => $otro->id]);

        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'miembros_presente' => 1,
                'miembros' => [$ana->id],
            ])
            ->assertSessionHasErrors('miembros.0');

        $this->assertDatabaseMissing('ministerios', ['nombre' => 'Alabanza']);
        $this->assertSame($otro->id, $ana->fresh()->id_ministerio);
    }

    public function test_quita_y_reagrega_miembros_actualizando_su_pertenencia(): void
    {
        $lider = $this->lider();
        $ana = $this->miembro('Ana Torres');
        $beto = $this->miembro('Beto Ruiz');

        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$ana->id, $beto->id],
            ]);

        $ministerio = Ministerio::where('nombre', 'Alabanza')->firstOrFail();

        $this->actingAs($this->administrador())
            ->put('/ministerios/'.$ministerio->id, [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$beto->id],
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertNull($ana->fresh()->id_ministerio);
        $this->assertSame($ministerio->id, $beto->fresh()->id_ministerio);
        $this->assertCount(1, $ministerio->fresh()->miembros);

        $this->actingAs($this->administrador())
            ->put('/ministerios/'.$ministerio->id, [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$ana->id, $beto->id],
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertSame($ministerio->id, $ana->fresh()->id_ministerio);
        $this->assertCount(2, $ministerio->fresh()->miembros);
    }

    public function test_rechaza_miembros_inexistentes(): void
    {
        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'miembros_presente' => 1,
                'miembros' => [9999],
            ])
            ->assertSessionHasErrors('miembros.0');

        $this->assertDatabaseMissing('ministerios', ['nombre' => 'Alabanza']);
    }

    public function test_eliminar_ministerio_libera_a_sus_miembros(): void
    {
        $ana = $this->miembro('Ana Torres');
        $ministerio = Ministerio::create(['nombre' => 'Alabanza']);
        $ana->update(['id_ministerio' => $ministerio->id]);

        $this->actingAs($this->administrador())
            ->delete('/ministerios/'.$ministerio->id)
            ->assertRedirect(route('ministerios.index'));

        $this->assertNull($ana->fresh()->id_ministerio);
        $this->assertSoftDeleted('ministerios', ['id' => $ministerio->id]);
    }

    public function test_sin_checkboxes_desmarca_todos_los_miembros_al_editar(): void
    {
        $lider = $this->lider();
        $ana = $this->miembro('Ana Torres');

        $this->actingAs($this->administrador())
            ->post('/ministerios', [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$ana->id],
            ]);

        $ministerio = Ministerio::where('nombre', 'Alabanza')->firstOrFail();

        $this->actingAs($this->administrador())
            ->put('/ministerios/'.$ministerio->id, [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertCount(0, $ministerio->fresh()->miembros);
        $this->assertNull($ana->fresh()->id_ministerio);
    }

    private function miembro(string $nombre): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }
}
