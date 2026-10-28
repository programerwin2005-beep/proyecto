<?php

namespace App\Http\Controllers;

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

                Asistencia::updateOrCreate(
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

        $inscritos = Inscripcion::where('curso_id', $sesion->id_curso)
            ->whereNull('deleted_at')
            ->pluck('miembro_id');

        DB::transaction(function () use ($inscritos, $sesion) {
            foreach ($inscritos as $miembroId) {
                Asistencia::updateOrCreate(
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
        $cursosQuery = Curso::whereHas('sesiones', fn ($q) => $q->whereNull('deleted_at'));

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            if ($docente && $docente->activo) {
                $cursosQuery->paraDocente($docente->id)
                    ->with(['sesiones' => fn ($q) => $q->paraDocente($docente->id)
                        ->whereNull('deleted_at')
                        ->orderBy('fecha')]);
            } else {
                $cursosQuery->whereRaw('1 = 0')->with('sesiones');
            }
        } else {
            $cursosQuery->with(['sesiones' => fn ($q) => $q->whereNull('deleted_at')->orderBy('fecha')]);
        }

        $cursos = $cursosQuery->orderBy('nombre')->get();

        $sesion = null;
        $inscritos = collect();
        $asistencias = collect();
        $resumen = ['Presente' => 0, 'Ausente' => 0, 'Tardanza' => 0, 'Justificado' => 0];

        if ($request->filled('sesion_id')) {
            $sesion = Sesion::with('curso', 'docente.miembro')->find($request->sesion_id);

            if ($sesion) {
                $this->autorizarSesion($sesion);

                $inscritos = Inscripcion::where('curso_id', $sesion->id_curso)
                    ->whereNull('deleted_at')
                    ->with('miembro')
                    ->get();

                $asistencias = Asistencia::where('sesion_id', $sesion->id)
                    ->get()
                    ->keyBy('miembro_id');

                $resumen = [
                    'Presente' => $asistencias->where('estado', 'Presente')->count(),
                    'Ausente' => $asistencias->where('estado', 'Ausente')->count(),
                    'Tardanza' => $asistencias->where('estado', 'Tardanza')->count(),
                    'Justificado' => $asistencias->where('estado', 'Justificado')->count(),
                ];
            }
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

            $pertenece = $docente && $docente->activo && (
                Inscripcion::where('miembro_id', $miembroId)
                    ->whereHas('curso.docentes', fn ($q) => $q->where('docentes.id', $docente->id))
                    ->exists()
                || Asistencia::where('miembro_id', $miembroId)
                    ->whereHas('sesion', fn ($q) => $q->where('id_docente', $docente->id))
                    ->exists()
            );

            abort_unless($pertenece, 403, 'No tienes acceso al historial de este miembro.');

            $query->whereHas('sesion', fn ($q) => $q->paraDocente($docente->id));
        }

        $asistencias = $query->orderByDesc('sesiones.fecha')->get();

        $miembro = Miembro::findOrFail($miembroId);

        $resumenPorCurso = $asistencias->groupBy(fn ($a) => $a->sesion?->curso?->nombre ?? 'Sin curso')
            ->map(fn ($lista) => [
                'total' => $lista->count(),
                'presentes' => $lista->where('estado', 'Presente')->count(),
                'porcentaje' => $lista->count() > 0
                    ? round(($lista->where('estado', 'Presente')->count() / $lista->count()) * 100, 1)
                    : 0,
            ]);

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
