<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $query = Asistencia::with(['miembro', 'sesion.curso'])
            ->join('sesiones', 'asistencias_sesion.sesion_id', '=', 'sesiones.id')
            ->select('asistencias_sesion.*');

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            $docente && $docente->activo
                ? $query->whereHas('sesion', fn ($q) => $q->paraDocente($docente->id))
                : $query->whereRaw('1 = 0');
        }

        $asistencias = $query
            ->when($request->filled('q'), fn ($q) => $q->whereHas('miembro', fn ($m) => $m->where('nombre', 'like', '%'.$request->q.'%')))
            ->when($request->filled('curso_id'), fn ($q) => $q->porCurso($request->curso_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('asistencias_sesion.estado', $request->estado))
            ->when($request->filled('desde'), fn ($q) => $q->where('sesiones.fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->where('sesiones.fecha', '<=', $request->hasta))
            ->orderByDesc('sesiones.fecha')
            ->paginate(15)
            ->withQueryString();

        $cursos = $this->cursosVisibles();

        return view('asistencia.index', compact('asistencias', 'cursos'));
    }

    public function store(Request $request, Sesion $sesion)
    {
        $this->autorizarSesion($sesion);
        $this->validarSesionNoFutura($sesion);

        $validated = $request->validate([
            'asistencia' => 'required|array',
            'asistencia.*.estado' => 'required|in:Presente,Ausente,Tardanza,Justificado',
            'asistencia.*.observacion' => 'nullable|string|max:255',
        ]);

        $inscritos = Inscripcion::where('curso_id', $sesion->id_curso)
            ->whereNull('deleted_at')
            ->pluck('miembro_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($validated, $sesion, $inscritos) {
            foreach ($validated['asistencia'] as $miembroId => $datos) {
                // Solo se aceptan miembros inscritos en el curso de la sesión.
                if (! in_array((int) $miembroId, $inscritos, true)) {
                    continue;
                }

                $valores = [
                    'estado' => $datos['estado'],
                    'observacion' => $datos['observacion'] ?? null,
                    'user_id' => auth()->id(),
                ];

                Asistencia::crearORestaurar(
                    ['sesion_id' => $sesion->id, 'miembro_id' => $miembroId],
                    $valores
                );
            }
        });

        return redirect()->route('asistencia.registrar', ['curso_id' => $sesion->id_curso, 'sesion_id' => $sesion->id])
            ->with('success', 'Asistencia guardada con éxito.');
    }

    public function marcarTodos(Sesion $sesion)
    {
        $this->autorizarSesion($sesion);
        $this->validarSesionNoFutura($sesion);

        $inscritos = Inscripcion::where('curso_id', $sesion->id_curso)
            ->whereNull('deleted_at')
            ->pluck('miembro_id');

        DB::transaction(function () use ($inscritos, $sesion) {
            foreach ($inscritos as $miembroId) {
                Asistencia::crearORestaurar(
                    ['sesion_id' => $sesion->id, 'miembro_id' => $miembroId],
                    ['estado' => 'Presente', 'user_id' => auth()->id()]
                );
            }
        });

        return redirect()->route('asistencia.registrar', ['curso_id' => $sesion->id_curso, 'sesion_id' => $sesion->id])
            ->with('success', 'Todos los inscritos marcados como presentes.');
    }

    public function registrar(Request $request)
    {
        $sesion = $request->filled('sesion_id')
            ? Sesion::with('curso', 'docente.miembro')->find($request->sesion_id)
            : null;

        if ($sesion) {
            $this->autorizarSesion($sesion);
            $this->validarSesionNoFutura($sesion);
        }

        $sesionSeleccionadaId = $sesion?->id;

        // Solo se ofrecen sesiones ya realizadas (no futuras) y sin asistencia
        // registrada. La sesión que se está consultando o editando se conserva
        // aunque ya la tenga.
        $filtrarSesiones = function ($query) use ($sesionSeleccionadaId) {
            $query->whereNull('deleted_at')
                ->whereDate('fecha', '<=', now()->toDateString())
                ->where(function ($sub) use ($sesionSeleccionadaId) {
                    $sub->whereDoesntHave('asistencias');

                    if ($sesionSeleccionadaId) {
                        $sub->orWhereKey($sesionSeleccionadaId);
                    }
                })
                ->orderBy('fecha');
        };

        $cursosQuery = Curso::query();

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            if ($docente && $docente->activo) {
                $cursosQuery->paraDocente($docente->id)
                    ->whereHas('sesiones', function ($q) use ($docente, $filtrarSesiones) {
                        $q->paraDocente($docente->id);
                        $filtrarSesiones($q);
                    })
                    ->with(['sesiones' => function ($q) use ($docente, $filtrarSesiones) {
                        $q->paraDocente($docente->id);
                        $filtrarSesiones($q);
                    }]);
            } else {
                $cursosQuery->whereRaw('1 = 0')->with('sesiones');
            }
        } else {
            $cursosQuery->whereHas('sesiones', $filtrarSesiones)
                ->with(['sesiones' => $filtrarSesiones]);
        }

        $cursos = $cursosQuery->orderBy('nombre')->get();

        $inscritos = collect();
        $asistencias = collect();
        $resumen = ['Presente' => 0, 'Ausente' => 0, 'Tardanza' => 0, 'Justificado' => 0];

        if ($sesion) {
            $inscritos = Inscripcion::where('curso_id', $sesion->id_curso)
                ->whereNull('deleted_at')
                ->with('miembro')
                ->get();

            $asistencias = Asistencia::where('sesion_id', $sesion->id)
                ->get()
                ->keyBy('miembro_id');

            $resumen = [
                'Presente' => $asistencias->filter(fn ($a) => $a->estado === EstadoAsistencia::Presente)->count(),
                'Ausente' => $asistencias->filter(fn ($a) => $a->estado === EstadoAsistencia::Ausente)->count(),
                'Tardanza' => $asistencias->filter(fn ($a) => $a->estado === EstadoAsistencia::Tardanza)->count(),
                'Justificado' => $asistencias->filter(fn ($a) => $a->estado === EstadoAsistencia::Justificado)->count(),
            ];
        }

        return view('asistencia.registrar', compact('cursos', 'sesion', 'inscritos', 'asistencias', 'resumen'));
    }

    public function historial(int $miembroId)
    {
        $query = Asistencia::with(['sesion.curso', 'miembro'])
            ->join('sesiones', 'asistencias_sesion.sesion_id', '=', 'sesiones.id')
            ->select('asistencias_sesion.*')
            ->porMiembro($miembroId);

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            $pertenece = $docente && $docente->activo
                && Asistencia::where('miembro_id', $miembroId)
                    ->whereHas('sesion', fn ($q) => $q->where('id_docente', $docente->id))
                    ->exists();

            abort_unless($pertenece, 403, 'No tienes acceso al historial de este miembro.');

            $query->whereHas('sesion', fn ($q) => $q->paraDocente($docente->id));
        }

        $asistencias = $query->orderByDesc('sesiones.fecha')->get();

        $miembro = Miembro::findOrFail($miembroId);

        $resumenPorCurso = $asistencias->groupBy(fn ($a) => $a->sesion?->curso?->nombre ?? 'Sin curso')
            ->map(function ($lista) {
                $presentes = $lista->filter(fn ($a) => $a->estado === EstadoAsistencia::Presente)->count();

                return [
                    'total' => $lista->count(),
                    'presentes' => $presentes,
                    'porcentaje' => $lista->count() > 0
                        ? round(($presentes / $lista->count()) * 100, 1)
                        : 0,
                ];
            });

        return view('asistencia.historial', compact('miembro', 'asistencias', 'resumenPorCurso'));
    }

    /**
     * Cursos que puede ver el usuario autenticado.
     */
    private function cursosVisibles()
    {
        $query = Curso::query()->orderBy('nombre');

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            $docente && $docente->activo
                ? $query->paraDocente($docente->id)
                : $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    /**
     * Impide registrar asistencia de sesiones con fecha futura.
     */
    private function validarSesionNoFutura(Sesion $sesion): void
    {
        abort_if(
            \Carbon\Carbon::parse($sesion->fecha)->startOfDay()->gt(now()->startOfDay()),
            403,
            'No se puede registrar asistencia de una fecha futura.'
        );
    }

    /**
     * Impide que un docente registre o consulte asistencia de sesiones ajenas.
     */
    private function autorizarSesion(Sesion $sesion): void
    {
        $user = auth()->user();

        if (! $user || ! $user->esDocente()) {
            return;
        }

        $docente = $user->docente;

        $pertenece = $docente && $docente->activo
            && Sesion::whereKey($sesion->id)->paraDocente($docente->id)->exists();

        abort_unless($pertenece, 403, 'No tienes acceso a esta sesión.');
    }
}
