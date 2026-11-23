<?php

namespace App\Services;

use App\Enums\EstadoActividad;
use App\Enums\EstadoSeguimiento;
use App\Models\Actividad;
use App\Models\SeguimientoActividad;
use Illuminate\Support\Facades\DB;

class ActividadTareasService
{
    public function guardarTarea(array $datos, ?int $id = null): SeguimientoActividad
    {
        return DB::transaction(function () use ($datos, $id) {
            $tarea = $id ? SeguimientoActividad::lockForUpdate()->findOrFail($id) : new SeguimientoActividad;
            $ids = array_filter([$tarea->id_actividad, $datos['id_actividad']]);
            $eventos = Actividad::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($eventos->contains('id', (int) $datos['id_actividad']), 404);

            // El porcentaje antiguo se conserva solo como dato histórico.
            unset($datos['porcentaje_avance']);
            $tarea->fill($datos)->save();
            foreach ($eventos as $evento) {
                $this->recalcular($evento);
            }

            return $tarea;
        }, 5);
    }

    public function eliminarTarea(int $id): void
    {
        DB::transaction(function () use ($id) {
            $tarea = SeguimientoActividad::lockForUpdate()->findOrFail($id);
            $evento = Actividad::lockForUpdate()->findOrFail($tarea->id_actividad);
            $tarea->delete();
            $this->recalcular($evento);
        }, 5);
    }

    public function completarTarea(int $id, bool $completada): SeguimientoActividad
    {
        return DB::transaction(function () use ($id, $completada) {
            $tarea = SeguimientoActividad::lockForUpdate()->findOrFail($id);
            $evento = Actividad::lockForUpdate()->findOrFail($tarea->id_actividad);
            // No convertir avances históricos en tareas por marcar una casilla.
            abort_unless(filled($tarea->nombre_tarea), 422, 'Revise y nombre la tarea histórica antes de completarla.');
            $tarea->estado = $completada ? EstadoSeguimiento::Completado : EstadoSeguimiento::EnProgreso;
            $tarea->save();
            $this->recalcular($evento);

            return $tarea;
        }, 5);
    }

    public function guardarActividad(array $datos, ?int $id = null): Actividad
    {
        return DB::transaction(function () use ($datos, $id) {
            $evento = $id ? Actividad::lockForUpdate()->findOrFail($id) : new Actividad;
            $accion = $datos['accion_estado'] ?? null;
            unset($datos['estado'], $datos['accion_estado']);
            $evento->fill($datos);
            if (! $evento->exists || $accion === 'reactivar') {
                $evento->estado = EstadoActividad::Programado;
            }
            if ($accion === 'cancelar') {
                $evento->estado = EstadoActividad::Cancelada;
            }
            $evento->save();
            $this->recalcular($evento);

            return $evento;
        }, 5);
    }

    // Se invoca dentro de la transacción, con la fila de actividad bloqueada.
    // La lectura bloqueante ve los últimos cambios incluso bajo REPEATABLE READ.
    private function recalcular(Actividad $evento): void
    {
        if ($evento->estado === EstadoActividad::Cancelada) {
            return;
        }

        $tareas = $evento->seguimientos()->lockForUpdate()->get();
        $sinRevisar = $tareas->contains(fn ($t) => ! filled($t->nombre_tarea));
        if ($tareas->isEmpty()) {
            $estado = EstadoActividad::Programado;
        } elseif (! $sinRevisar && $tareas->every(fn ($t) => $t->estado === EstadoSeguimiento::Completado)) {
            $estado = EstadoActividad::Completada;
        } elseif ($sinRevisar || $tareas->contains(fn ($t) => $t->estado !== EstadoSeguimiento::Pendiente)
            || in_array($evento->estado, [EstadoActividad::Completada, EstadoActividad::EnProgreso], true)) {
            $estado = EstadoActividad::EnProgreso;
        } else {
            $estado = EstadoActividad::Programado;
        }
        $evento->estado = $estado;
        $evento->save();
    }
}
