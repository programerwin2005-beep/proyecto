<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La eliminación es solo lógica: un registro eliminado no vuelve a activarse.
 */
class EliminacionLogicaTest extends TestCase
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

    private function miembro(string $nombre = 'Miembro de Prueba'): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    private function curso(): Curso
    {
        return Curso::create([
            'nombre' => 'Curso de Prueba',
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
        ]);
    }

    public function test_no_se_reinscribe_una_inscripcion_eliminada(): void
    {
        $miembro = $this->miembro();
        $curso = $this->curso();

        $inscripcion = Inscripcion::create([
            'miembro_id' => $miembro->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->subWeek()->toDateString(),
            'estado' => 'Activo',
        ]);

        $inscripcion->delete();

        $this->actingAs($this->admin)
            ->post(route('inscripciones.store', $curso), [
                'miembro_id' => $miembro->id,
                'fecha_inscripcion' => now()->toDateString(),
            ])
            ->assertSessionHas('error', 'Ya existe una inscripción eliminada para este miembro en este curso.');

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertSoftDeleted('inscripciones', ['id' => $inscripcion->id]);
    }

    public function test_no_duplica_una_inscripcion_activa(): void
    {
        $miembro = $this->miembro();
        $curso = $this->curso();

        Inscripcion::create([
            'miembro_id' => $miembro->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->actingAs($this->admin)
            ->post(route('inscripciones.store', $curso), [
                'miembro_id' => $miembro->id,
                'fecha_inscripcion' => now()->toDateString(),
            ])
            ->assertSessionHas('error', 'El miembro ya está inscrito en este curso.');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_no_se_registra_un_docente_eliminado(): void
    {
        $miembro = $this->miembro();

        $docente = Docente::create([
            'id_miembro' => $miembro->id,
            'especialidad' => 'Matemática',
            'activo' => true,
        ]);

        $docente->delete();

        $this->actingAs($this->admin)
            ->post(route('docentes.store'), [
                'id_miembro' => $miembro->id,
                'especialidad' => 'Física',
                'titulo' => 'Licenciado',
                'activo' => 1,
            ])
            ->assertSessionHas('error', 'Ya existe un docente eliminado para este miembro.');

        $this->assertDatabaseCount('docentes', 1);
        $this->assertSoftDeleted('docentes', ['id' => $docente->id]);
    }
}
