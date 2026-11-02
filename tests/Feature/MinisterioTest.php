<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_crea_un_ministerio_con_miembros_solo_en_el_pivote(): void
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
        $this->assertSame(2, DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->whereNull('deleted_at')
            ->count());

        $this->assertNotNull(DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->where('miembro_id', $ana->id)
            ->value('fecha_inicio'));

        // El líder dirige el ministerio, pero no se guarda como miembro.
        $integrantes = DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->whereNull('deleted_at')
            ->pluck('miembro_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing([$ana->id, $beto->id], $integrantes);
    }

    public function test_quita_y_reagrega_miembros_sin_duplicar_y_conserva_la_fecha_inicio(): void
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

        $fechaOriginal = DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->where('miembro_id', $ana->id)
            ->value('fecha_inicio');

        $this->actingAs($this->administrador())
            ->put('/ministerios/'.$ministerio->id, [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$beto->id],
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertSoftDeleted('miembro_ministerio', [
            'ministerio_id' => $ministerio->id,
            'miembro_id' => $ana->id,
        ]);
        $this->assertCount(1, $ministerio->fresh()->miembros);

        $this->actingAs($this->administrador())
            ->put('/ministerios/'.$ministerio->id, [
                'nombre' => 'Alabanza',
                'id_lider_iglesia' => $lider->id,
                'miembros_presente' => 1,
                'miembros' => [$ana->id, $beto->id],
            ])
            ->assertRedirect(route('ministerios.index'));

        $this->assertNotSoftDeleted('miembro_ministerio', [
            'ministerio_id' => $ministerio->id,
            'miembro_id' => $ana->id,
        ]);
        $this->assertCount(2, $ministerio->fresh()->miembros);

        $this->assertSame(1, DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->where('miembro_id', $ana->id)
            ->count());

        $this->assertSame($fechaOriginal, DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->where('miembro_id', $ana->id)
            ->value('fecha_inicio'));
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
        $this->assertSoftDeleted('miembro_ministerio', [
            'ministerio_id' => $ministerio->id,
            'miembro_id' => $ana->id,
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
}
