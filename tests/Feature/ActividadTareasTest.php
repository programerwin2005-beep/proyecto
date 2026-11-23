<?php

namespace Tests\Feature;

use App\Enums\EstadoActividad;
use App\Models\Actividad;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\SeguimientoActividad;
use App\Models\User;
use App\Services\ActividadTareasService;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActividadTareasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Role::firstOrCreate(['nombre' => 'Administrador'], ['estado' => 'Activo', 'nivel_acceso' => 1]);
        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol);
        $this->actingAs($usuario);
    }

    private function evento(): Actividad
    {
        $ministerio = Ministerio::create(['nombre' => 'Juventud']);
        $this->post(route('actividades.store'), [
            'id_ministerio' => $ministerio->id,
            'nombre' => 'Encuentro de jóvenes',
            'fecha' => today()->toDateString(),
            'estado' => 'Completada', // Un cliente no puede forzar el cierre.
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Actividad::latest('id')->firstOrFail();
    }

    private function datos(Actividad $evento, string $nombre = 'Preparar el salón', string $estado = 'Pendiente'): array
    {
        return [
            'id_actividad' => $evento->id,
            'nombre_tarea' => $nombre,
            'fecha_registro' => today()->toDateString(),
            'responsable' => 'Ana Pérez',
            'observacion' => 'Observación conservada',
            'estado' => $estado,
        ];
    }

    private function tarea(Actividad $evento, string $nombre = 'Preparar el salón'): SeguimientoActividad
    {
        $this->post(route('seguimiento.store'), $this->datos($evento, $nombre))
            ->assertSessionHasNoErrors()->assertRedirect();

        return SeguimientoActividad::latest('id')->firstOrFail();
    }

    public function test_encuentro_de_jovenes_con_tres_tareas_y_reapertura(): void
    {
        $evento = $this->evento();
        $this->assertSame(EstadoActividad::Programado, $evento->estado);
        $tareas = collect(['Preparar el salón', 'Realizar la charla', 'Recoger los materiales'])
            ->map(fn ($nombre) => $this->tarea($evento, $nombre));
        $this->assertSame(EstadoActividad::Programado, $evento->fresh()->estado);
        $this->assertEquals(0, $tareas[0]->porcentaje_avance);

        foreach ($tareas as $i => $tarea) {
            $this->put(route('seguimiento.update', $tarea), $this->datos($evento, $tarea->nombre_tarea, 'Completado'))
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertEquals(0, $tarea->fresh()->porcentaje_avance);
            $this->assertSame($i === 2 ? EstadoActividad::Completada : EstadoActividad::EnProgreso, $evento->fresh()->estado);
        }
        $this->put(route('seguimiento.update', $tareas[0]), $this->datos($evento))
            ->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $this->assertDatabaseCount('seguimiento_actividades', 3);
        $this->get(route('actividades.show', $evento))->assertOk()
            ->assertSee('Preparar el salón')->assertSee('Ana Pérez')->assertSee('Actualizar tarea');
        $this->get(route('seguimiento.create', ['id_actividad' => $evento->id]))
            ->assertOk()->assertSee('nombre_tarea')->assertSee('Pendiente');
        $this->get(route('seguimiento.edit', $tareas[0]))->assertOk();
    }

    public function test_detenida_cancelada_y_reactivacion_explicita(): void
    {
        $evento = $this->evento();
        $tarea = $this->tarea($evento);
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento, estado: 'Detenido'))->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $datos = $evento->only(['id_ministerio', 'nombre', 'fecha']);
        $this->put(route('actividades.update', $evento), $datos + ['accion_estado' => 'cancelar'])->assertSessionHasNoErrors();
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento, estado: 'Completado'))->assertSessionHasNoErrors();
        $this->put(route('actividades.update', $evento), $datos + ['estado' => 'Programado'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::Cancelada, $evento->fresh()->estado);
        $this->put(route('actividades.update', $evento), $datos + ['accion_estado' => 'reactivar'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::Completada, $evento->fresh()->estado);
        $this->tarea($evento, 'Trabajo adicional');
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $this->put(route('actividades.update', $evento), $datos + ['estado' => 'Completada'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
    }

    public function test_mover_y_eliminar_recalcula_ambos_eventos(): void
    {
        $origen = $this->evento();
        $destino = $this->evento();
        $tarea = $this->tarea($origen);
        $this->put(route('seguimiento.update', $tarea), $this->datos($destino, estado: 'Completado'))->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::Programado, $origen->fresh()->estado);
        $this->assertSame(EstadoActividad::Completada, $destino->fresh()->estado);
        $pendiente = $this->tarea($destino);
        $this->delete(route('seguimiento.destroy', $pendiente))->assertRedirect();
        $this->assertSame(EstadoActividad::Completada, $destino->fresh()->estado);
        $this->assertSoftDeleted($pendiente);
        $this->delete(route('seguimiento.destroy', $tarea))->assertRedirect();
        $this->assertSame(EstadoActividad::Programado, $destino->fresh()->estado);
    }

    public function test_migracion_conserva_historicos_y_no_los_usa_para_cerrar(): void
    {
        $evento = $this->evento();
        $migration = require database_path('migrations/2026_11_22_000003_add_nombre_tarea_to_seguimiento_actividades.php');
        $migration->down();
        $datos = $this->datos($evento, estado: 'Completado');
        unset($datos['nombre_tarea']);
        $datos['porcentaje_avance'] = 100;
        $id = DB::table('seguimiento_actividades')->insertGetId($datos);
        $anterior = $datos;
        $anterior['porcentaje_avance'] = 25;
        $anterior['estado'] = 'En Progreso';
        $anterior['observacion'] = 'Avance anterior de la misma tarea';
        $idAnterior = DB::table('seguimiento_actividades')->insertGetId($anterior);
        $evento->update(['estado' => 'Completada']);
        $antes = (array) DB::table('seguimiento_actividades')->find($id);
        $migration->up();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $despues = (array) DB::table('seguimiento_actividades')->find($id);
        $this->assertNull($despues['nombre_tarea']);
        unset($despues['nombre_tarea']);
        $this->assertSame($antes, $despues);
        $this->assertDatabaseHas('seguimiento_actividades', $anterior + ['id' => $idAnterior, 'nombre_tarea' => null]);
        $tarea = $this->tarea($evento);
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento, estado: 'Completado'))->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $this->get(route('seguimiento.edit', $id))->assertOk()->assertSee('histórico pendiente de revisión');
        $this->put(route('seguimiento.update', $id), $this->datos($evento, 'Revisión histórica confirmada', 'Completado'))->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
        $this->delete(route('seguimiento.destroy', $idAnterior))->assertRedirect();
        $this->assertSoftDeleted('seguimiento_actividades', ['id' => $idAnterior, 'observacion' => $anterior['observacion']]);
        $this->assertSame(EstadoActividad::Completada, $evento->fresh()->estado);
    }

    public function test_kpi_promedia_todas_las_tareas_vigentes(): void
    {
        $evento = $this->evento();
        $this->tarea($evento);
        $this->tarea($evento, 'Realizar la charla');
        $ultima = $this->tarea($evento, 'Recoger los materiales');
        $this->put(route('seguimiento.update', $ultima), $this->datos($evento, $ultima->nombre_tarea, 'Completado'))->assertSessionHasNoErrors();
        $hoy = today()->toDateString();
        $kpi = app(KpiService::class)->ministerios($hoy, $hoy, $hoy);
        $this->assertEquals(33.3, $kpi['avance_promedio']);
        $eliminada = $this->tarea($evento, 'Descartada');
        $this->delete(route('seguimiento.destroy', $eliminada));
        $this->assertEquals(33.3, app(KpiService::class)->ministerios($hoy, $hoy, $hoy)['avance_promedio']);
    }

    public function test_nombre_obligatorio_y_transaccion_revierte_si_falla_evento(): void
    {
        $evento = $this->evento();
        $datos = $this->datos($evento);
        unset($datos['nombre_tarea']);
        $this->post(route('seguimiento.store'), $datos)->assertSessionHasErrors('nombre_tarea');
        Actividad::saving(function () {
            throw new \RuntimeException('Fallo simulado');
        });
        try {
            app(ActividadTareasService::class)->guardarTarea($this->datos($evento));
            $this->fail('Debió fallar');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        } finally {
            Actividad::flushEventListeners();
        }
        $this->assertDatabaseCount('seguimiento_actividades', 0);
        $this->assertSame(EstadoActividad::Programado, $evento->fresh()->estado);
    }

    public function test_cancelacion_sin_tareas_y_reapertura_de_la_unica_tarea(): void
    {
        $evento = $this->evento();
        $datos = $evento->only(['id_ministerio', 'nombre', 'fecha']);
        $this->put(route('actividades.update', $evento), $datos + ['accion_estado' => 'cancelar'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::Cancelada, $evento->fresh()->estado);
        $this->put(route('actividades.update', $evento), $datos + ['accion_estado' => 'reactivar'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::Programado, $evento->fresh()->estado);
        $tarea = $this->tarea($evento);
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento, estado: 'Completado'))->assertSessionHasNoErrors();
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento))->assertSessionHasNoErrors();
        $this->assertSame(EstadoActividad::EnProgreso, $evento->fresh()->estado);
    }

    public function test_sin_permisos_no_puede_modificar_tareas(): void
    {
        $evento = $this->evento();
        $tarea = $this->tarea($evento);
        $this->actingAs(User::factory()->create());
        $this->post(route('seguimiento.store'), $this->datos($evento))->assertForbidden();
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento))->assertForbidden();
        $this->delete(route('seguimiento.destroy', $tarea))->assertForbidden();
        $this->patch(route('seguimiento.completar', $tarea), ['completada' => 1])->assertForbidden();
        $this->assertDatabaseCount('seguimiento_actividades', 1);
    }

    public function test_casillas_conteo_terminado_y_reapertura_sin_porcentajes(): void
    {
        $evento = $this->evento();
        $this->get(route('actividades.index'))->assertOk()->assertDontSee('Terminado');
        $this->get(route('actividades.show', $evento))->assertSee('0 de 0 tareas completadas');
        $tareas = collect(['Preparar el salón', 'Realizar la charla', 'Recoger los materiales'])
            ->map(fn ($nombre) => $this->tarea($evento, $nombre));
        foreach ($tareas->take(2) as $tarea) {
            $this->from(route('actividades.show', $evento))
                ->patch(route('seguimiento.completar', $tarea), ['completada' => 1])
                ->assertRedirect(route('actividades.show', $evento));
        }
        $this->get(route('actividades.show', $evento))->assertSee('2 de 3 tareas completadas')
            ->assertSee('Tarea completada')->assertDontSee('porcentaje_avance');
        $this->get(route('actividades.index'))->assertDontSee('Terminado');
        $this->patch(route('seguimiento.completar', $tareas[2]), ['completada' => 1])->assertRedirect();
        $this->get(route('actividades.index'))->assertSee('Terminado');
        $this->assertSame(EstadoActividad::Completada, $evento->fresh()->estado);
        $this->patch(route('seguimiento.completar', $tareas[0]), ['completada' => 0])->assertRedirect();
        $this->get(route('actividades.index'))->assertSee('En Progreso')->assertDontSee('Terminado');
        $this->get(route('seguimiento.index'))->assertSee('Tarea completada')->assertDontSee('% Avance');
        $this->get(route('seguimiento.create'))->assertDontSee('porcentaje_avance');
        $this->get(route('seguimiento.edit', $tareas[0]))->assertDontSee('porcentaje_avance');
        $this->get(route('seguimiento.show', $tareas[0]))->assertDontSee('Porcentaje de Avance');
    }

    public function test_casillas_conservan_porcentaje_historico_y_cancelacion(): void
    {
        $evento = $this->evento();
        $tarea = $this->tarea($evento);
        $tarea->update(['porcentaje_avance' => 37]);
        $evento->update(['estado' => 'Cancelada']);
        foreach ([1, 0] as $completada) {
            $this->patch(route('seguimiento.completar', $tarea), compact('completada'))->assertRedirect();
            $this->assertEquals(37, $tarea->fresh()->porcentaje_avance);
            $this->assertSame(EstadoActividad::Cancelada, $evento->fresh()->estado);
        }
        $this->put(route('seguimiento.update', $tarea), $this->datos($evento))->assertSessionHasNoErrors();
        $this->assertEquals(37, $tarea->fresh()->porcentaje_avance);
        $this->get(route('actividades.index'))->assertDontSee('Terminado');
        $tarea->update(['nombre_tarea' => null]);
        $this->patch(route('seguimiento.completar', $tarea), ['completada' => 1])->assertStatus(422);
    }
}
