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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiderIglesiaTest extends TestCase
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

    private function cargo(string $nombre = 'Pastor'): Cargo
    {
        return Cargo::create(['nombre' => $nombre, 'nivel_jerarquico' => 1]);
    }

    private function miembro(string $nombre): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    public function test_crea_un_lider_con_sus_propios_datos_sin_vinculo_a_miembro(): void
    {
        $this->miembro('Ana Torres');

        $this->actingAs($this->administrador())
            ->post('/lideres', [
                'id_cargo' => $this->cargo()->id,
                'nombre' => 'Líder Manual',
                'telefono' => '70123456',
                'fecha_inicio' => '2026-01-01',
                'activo' => true,
            ])
            ->assertRedirect(route('lideres.index'));

        $lider = LiderIglesia::where('nombre', 'Líder Manual')->firstOrFail();

        $this->assertSame('70123456', $lider->telefono);
        $this->assertFalse(Schema::hasColumn('lideres_iglesia', 'id_miembro'));
    }

    public function test_el_formulario_de_lider_no_ofrece_selector_de_miembro(): void
    {
        $this->miembro('Ana Torres');

        $this->actingAs($this->administrador())
            ->get('/lideres/create')
            ->assertOk()
            ->assertDontSee('Miembro vinculado')
            ->assertDontSee('Ana Torres');
    }

    public function test_el_lider_dirige_el_ministerio_y_los_miembros_solo_integran_el_pivote(): void
    {
        $lider = LiderIglesia::create([
            'id_cargo' => $this->cargo()->id,
            'nombre' => 'Wilson',
            'fecha_inicio' => '2026-01-01',
            'activo' => true,
        ]);

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

        $this->assertSame($lider->id, $ministerio->id_lider_iglesia);

        $integrantes = DB::table('miembro_ministerio')
            ->where('ministerio_id', $ministerio->id)
            ->whereNull('deleted_at')
            ->pluck('miembro_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing([$ana->id, $beto->id], $integrantes);
        $this->assertFalse(Schema::hasColumn('lideres_iglesia', 'id_miembro'));
    }

    public function test_rechaza_un_lider_sin_nombre(): void
    {
        $this->actingAs($this->administrador())
            ->post('/lideres', [
                'id_cargo' => $this->cargo()->id,
                'fecha_inicio' => '2026-01-01',
                'activo' => true,
            ])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('lideres_iglesia', 0);
    }
}
