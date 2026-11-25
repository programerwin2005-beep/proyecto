<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteMiembrosFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private Miembro $miembro;

    private Curso $curso;

    private Ministerio $ministerio;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Role::firstOrCreate(['nombre' => 'Administrador'], ['estado' => 'Activo', 'nivel_acceso' => 1]);
        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol);
        $this->actingAs($usuario);
        $this->miembro = Miembro::create(['nombre' => 'Ana Pérez', 'email' => 'ana@example.test', 'telefono' => '76543210', 'fecha_ingreso' => today()->toDateString(), 'estado' => 'Activo']);
        Miembro::create(['nombre' => 'Luis Sin Vinculos', 'fecha_ingreso' => today()->toDateString(), 'estado' => 'Inactivo']);
        $this->curso = Curso::create(['nombre' => 'Formación bíblica', 'f_inicio' => today()->toDateString(), 'cupo_max' => 20]);
        Inscripcion::create(['miembro_id' => $this->miembro->id, 'curso_id' => $this->curso->id, 'fecha_inscripcion' => today()->toDateString(), 'estado' => 'Activo']);
        Contrato::create(['id_miembro' => $this->miembro->id, 'tipo_compensacion' => 'Salario', 'salario' => 500, 'fecha_inicio' => today()->toDateString(), 'activo' => true]);
        $this->ministerio = Ministerio::create(['nombre' => 'Alabanza']);
        $this->miembro->update(['id_ministerio' => $this->ministerio->id]);
    }

    public function test_busca_y_muestra_cursos_contratos_y_ministerios(): void
    {
        foreach (['Ana Pérez', 'ana@example.test', '76543210'] as $texto) {
            $this->get(route('reportes.miembros', ['q' => $texto]))
                ->assertOk()->assertSee('Ana Pérez')->assertDontSee('Luis Sin Vinculos')
                ->assertSee('Formación bíblica (Activo)')->assertSee('Salario')->assertSee('Vigente')
                ->assertViewHas('miembros', fn ($miembros) => $miembros->count() === 1 && $miembros->first()->ministerio?->id === $this->ministerio->id)
                ->assertViewHas('totales', fn ($totales) => $totales['total'] === 1 && $totales['activos'] === 1);
        }
    }

    public function test_combina_filtros_y_detecta_miembros_sin_vinculos(): void
    {
        $this->get(route('reportes.miembros', ['curso_id' => $this->curso->id, 'contratos' => 'con', 'ministerio_id' => $this->ministerio->id, 'estado' => 'Activo']))
            ->assertOk()->assertViewHas('miembros', fn ($miembros) => $miembros->pluck('id')->all() === [$this->miembro->id]);
        $this->get(route('reportes.miembros', ['contratos' => 'sin', 'ministerios' => 'sin']))
            ->assertOk()->assertSee('Luis Sin Vinculos')->assertDontSee('Ana Pérez')
            ->assertSee('Sin inscripciones')->assertSee('Sin contratos')->assertSee('Sin ministerio actual');
    }

    public function test_excluye_relaciones_eliminadas_y_miembros_sin_ministerio(): void
    {
        $this->miembro->update(['id_ministerio' => null]);
        $this->get(route('reportes.miembros', ['ministerios' => 'con']))->assertOk()
            ->assertViewHas('miembros', fn ($filas) => $filas->isEmpty());
        $this->get(route('reportes.miembros', ['ministerios' => 'sin']))->assertOk()
            ->assertViewHas('miembros', fn ($filas) => $filas->pluck('id')->contains($this->miembro->id));

        $this->miembro->inscripciones->first()->delete();
        $this->miembro->contratos->first()->delete();
        $this->get(route('reportes.miembros', ['curso_id' => $this->curso->id]))->assertOk()
            ->assertViewHas('miembros', fn ($filas) => $filas->isEmpty());
        $this->get(route('reportes.miembros', ['contratos' => 'con']))->assertOk()
            ->assertViewHas('miembros', fn ($filas) => $filas->isEmpty());
    }

    public function test_valida_filtros(): void
    {
        $this->get(route('reportes.miembros', ['curso_id' => 99999, 'contratos' => 'invalido']))
            ->assertSessionHasErrors(['curso_id', 'contratos']);
        $this->get(route('reportes.miembros', ['desde' => '2026-12-01', 'hasta' => '2026-01-01']))
            ->assertSessionHasErrors('hasta');
    }

    public function test_busqueda_muestra_docente_y_solo_sus_sesiones_pendientes(): void
    {
        $docente = Docente::create(['id_miembro' => $this->miembro->id, 'activo' => true]);
        $datos = ['id_docente' => $docente->id, 'id_curso' => $this->curso->id, 'hora' => '10:30'];
        Sesion::create($datos + ['tema' => 'Tema futuro pendiente', 'fecha' => today()->addDay()->toDateString()]);
        Sesion::create($datos + ['tema' => 'Tema atrasado pendiente', 'fecha' => today()->subDay()->toDateString()]);
        Sesion::create($datos + ['tema' => 'Tema de hoy pendiente', 'fecha' => today()->toDateString()]);
        $realizada = Sesion::create($datos + ['tema' => 'Tema ya realizado', 'fecha' => today()->subDays(2)->toDateString()]);
        Asistencia::create(['miembro_id' => $this->miembro->id, 'sesion_id' => $realizada->id, 'estado' => 'Presente']);
        $eliminada = Sesion::create($datos + ['tema' => 'Tema eliminado', 'fecha' => today()->toDateString()]);
        $eliminada->delete();
        Sesion::create(['id_curso' => $this->curso->id, 'tema' => 'Tema sin docente', 'fecha' => today()->toDateString(), 'hora' => '12:00']);

        $this->get(route('reportes.miembros', ['q' => 'Ana Pérez']))
            ->assertOk()->assertSee('Docente activo')->assertSee('3 sesiones asignadas pendientes')
            ->assertSee('Tema futuro pendiente')->assertSee('Tema atrasado pendiente')->assertSee('10:30')
            ->assertSee('Tema de hoy pendiente')->assertSee('Atrasada')->assertSee('En Progreso')->assertSee('Programado')
            ->assertDontSee('Tema ya realizado')->assertDontSee('Tema eliminado')->assertDontSee('Tema sin docente')
            ->assertDontSee('Total de miembros');
    }

    public function test_docente_sin_sesiones_y_miembro_no_docente(): void
    {
        Docente::create(['id_miembro' => $this->miembro->id, 'activo' => false]);
        $this->get(route('reportes.miembros', ['q' => 'Ana Pérez']))
            ->assertOk()->assertSee('Docente inactivo')->assertSee('No tiene sesiones asignadas pendientes.');
        $this->get(route('reportes.miembros', ['q' => 'Luis Sin Vinculos']))
            ->assertOk()->assertDontSee('Sesiones pendientes del docente');
    }
}
