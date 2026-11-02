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
 * La eliminación es lógica y recrear la misma relación restaura el registro
 * eliminado en lugar de duplicarlo o fallar por el índice UNIQUE.
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

    public function test_reinscribir_restaura_la_inscripcion_eliminada(): void
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
            ->assertRedirect(route('inscripciones.gestion', $curso->id))
            ->assertSessionHas('success', 'Inscripción reactivada con éxito.');

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertNotSoftDeleted('inscripciones', ['id' => $inscripcion->id]);

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'Activo',
            'fecha_inscripcion' => now()->toDateString(),
        ]);
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

    public function test_registrar_un_docente_eliminado_lo_restaura(): void
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
            ->assertRedirect(route('docentes.index'))
            ->assertSessionHas('success', 'Docente reactivado con éxito.');

        $this->assertDatabaseCount('docentes', 1);
        $this->assertNotSoftDeleted('docentes', ['id' => $docente->id]);

        $this->assertDatabaseHas('docentes', [
            'id' => $docente->id,
            'especialidad' => 'Física',
            'titulo' => 'Licenciado',
        ]);
    }
}
