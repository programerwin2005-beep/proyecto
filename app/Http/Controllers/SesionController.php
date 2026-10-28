<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Inscripcion;
use App\Models\Sesion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    public function index()
    {
        $query = Sesion::with(['curso', 'docente']);

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            $docente && $docente->activo
                ? $query->paraDocente($docente->id)
                : $query->whereRaw('1 = 0');
        }

        $sesiones = $query->paginate(10);

        return view('sesiones.index', compact('sesiones'));
    }

    public function create()
    {
        $cursos = Curso::with('docentes.miembro')->get();
        $docentes = Docente::with('miembro')->where('activo', true)->get()->sortBy('miembro.nombre')->values();

        $docentesPorCurso = $cursos->mapWithKeys(fn ($c) => [
            $c->id => $c->docentes->pluck('id')->toArray(),
        ]);

        $cursosData = $this->cursosParaJS($cursos);

        return view('sesiones.create', compact('cursos', 'docentes', 'docentesPorCurso', 'cursosData'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_curso' => 'required|exists:cursos,id',
            'id_docente' => 'nullable|exists:docentes,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'tema' => 'required|string|max:255',
            'observacion' => 'nullable|string',
        ]);

        $this->validarHorarioCurso($validated);

        Sesion::create($validated);

        return redirect()->route('sesiones.index')->with('success', 'Sesión creada con éxito.');
    }

    public function show($id)
    {
        $sesion = Sesion::with(['curso', 'docente.miembro'])->withTrashed()->findOrFail($id);

        $this->autorizarAcceso($sesion);

        $inscritos = Inscripcion::with('miembro')
            ->where('curso_id', $sesion->id_curso)
            ->whereNull('deleted_at')
            ->orderBy('fecha_inscripcion')
            ->get();

        $asistencias = DB::table('asistencias_sesion')
            ->where('sesion_id', $sesion->id)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('miembro_id');

        $sesionPasada = Carbon::parse($sesion->fecha)->lt(now()->startOfDay());

        $resumen = [
            'Presente' => 0,
            'Ausente' => 0,
            'Tardanza' => 0,
            'Justificado' => 0,
        ];

        foreach ($asistencias as $a) {
            if (isset($resumen[$a->estado])) {
                $resumen[$a->estado]++;
            }
        }

        return view('sesiones.show', compact('sesion', 'inscritos', 'asistencias', 'resumen', 'sesionPasada'));
    }

    public function edit($id)
    {
        $sesion = Sesion::withTrashed()->findOrFail($id);
        $cursos = Curso::with('docentes.miembro')->get();
        $docentes = Docente::with('miembro')->where('activo', true)->get()->sortBy('miembro.nombre')->values();

        $docentesPorCurso = $cursos->mapWithKeys(fn ($c) => [
            $c->id => $c->docentes->pluck('id')->toArray(),
        ]);

        $cursosData = $this->cursosParaJS($cursos);

        return view('sesiones.edit', compact('sesion', 'cursos', 'docentes', 'docentesPorCurso', 'cursosData'));
    }

    private function cursosParaJS($cursos)
    {
        return $cursos->mapWithKeys(fn ($c) => [
            $c->id => [
                'hora_inicio' => $c->hora_inicio ? substr((string) $c->hora_inicio, 0, 5) : null,
                'hora_fin' => $c->hora_fin ? substr((string) $c->hora_fin, 0, 5) : null,
                'dias_semana' => $c->dias_semana ?? [],
                'dias_nombres' => $c->dias_nombres,
                'f_inicio' => $c->f_inicio ? Carbon::parse($c->f_inicio)->format('Y-m-d') : null,
                'f_fin' => $c->f_fin ? Carbon::parse($c->f_fin)->format('Y-m-d') : null,
                'docentes' => $c->docentes->pluck('id')->toArray(),
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $sesion = Sesion::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'id_curso' => 'required|exists:cursos,id',
            'id_docente' => 'nullable|exists:docentes,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'tema' => 'required|string|max:255',
            'observacion' => 'nullable|string',
        ]);

        $this->validarHorarioCurso($validated, $sesion->id);

        $sesion->update($validated);

        return redirect()->route('sesiones.index')->with('success', 'Sesión actualizada con éxito.');
    }

    public function destroy($id)
    {
        $sesion = Sesion::findOrFail($id);
        $sesion->delete();

        return redirect()->route('sesiones.index')->with('success', 'Sesión eliminada lógicamente.');
    }


    public function generar(Curso $curso)
    {
        $errorPrevio = null;

        if (! $curso->f_fin) {
            $errorPrevio = 'El curso necesita fecha de fin para poder generar sesiones automáticamente.';
        } elseif (! $curso->tieneHorario()) {
            $errorPrevio = 'Configura primero los días y hora de inicio del curso.';
        }

        if ($errorPrevio !== null) {
            return back()->with('error', $errorPrevio);
        }

        $existentes = Sesion::withTrashed()
            ->where('id_curso', $curso->id)
            ->get()
            ->map(fn ($s) => Carbon::parse($s->fecha)->toDateString().' '.substr((string) $s->hora, 0, 5))
            ->all();

        $creadas = 0;
        $fecha = Carbon::parse($curso->f_inicio);
        $fin = Carbon::parse($curso->f_fin);
        $hora = substr((string) $curso->hora_inicio, 0, 5);

        // Si el curso tiene un único docente, las sesiones generadas quedan
        // asignadas a él; con varios docentes se asigna manualmente.
        $docenteUnico = $curso->docentes()->count() === 1
            ? $curso->docentes()->first()
            : null;

        $diasCurso = array_map('intval', $curso->dias_semana ?? []);

        while ($fecha->lte($fin)) {
            if (in_array($fecha->dayOfWeekIso, $diasCurso, true)) {
                $clave = $fecha->toDateString().' '.$hora;

                if (! in_array($clave, $existentes)) {
                    Sesion::create([
                        'id_curso' => $curso->id,
                        'id_docente' => $docenteUnico?->id,
                        'fecha' => $fecha->toDateString(),
                        'hora' => $curso->hora_inicio,
                        'tema' => 'Sesión '.($creadas + 1),
                    ]);
                    $creadas++;
                }
            }
            $fecha->addDay();
        }

        if ($creadas === 0) {
            return back()->with('error', 'No se creó ninguna sesión nueva (ya existen todas las del horario asignado).');
        }

        return redirect()->route('sesiones.index')
            ->with('success', "Se generaron {$creadas} sesiones automáticamente para el curso {$curso->nombre}.");
    }

    /**
     * Impide que un docente consulte sesiones de cursos que no tiene asignados.
     */
    private function autorizarAcceso(Sesion $sesion): void
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

    private function validarRangoFechas(Curso $curso, Carbon $fecha, array &$errores): void
    {
        if ($fecha->lt(Carbon::parse($curso->f_inicio))) {
            $errores['fecha'] = 'La fecha es anterior al inicio del curso ('.Carbon::parse($curso->f_inicio)->format('d/m/Y').').';
        } elseif ($curso->f_fin && $fecha->gt(Carbon::parse($curso->f_fin))) {
            $errores['fecha'] = 'La fecha es posterior al fin del curso ('.Carbon::parse($curso->f_fin)->format('d/m/Y').').';
        }
    }

    private function validarDiaHorario(Curso $curso, Carbon $fecha, string $horaInput, array &$errores): void
    {
        $diasCurso = array_map('intval', $curso->dias_semana ?? []);

        if (! empty($diasCurso) && ! in_array($fecha->dayOfWeekIso, $diasCurso, true)) {
            $errores['fecha'] = 'Este curso se dicta los días '.$curso->dias_nombres.'. Elige una fecha dentro del horario asignado.';
        }

        if ($curso->hora_inicio && $horaInput !== substr((string) $curso->hora_inicio, 0, 5)) {
            $errores['hora'] = 'Las sesiones de este curso son a las '.substr((string) $curso->hora_inicio, 0, 5).'.';
        }
    }

    private function validarSesionDuplicada(Curso $curso, array $datos, ?int $ignorarSesionId, array &$errores): void
    {
        $queryDuplicada = Sesion::where('id_curso', $curso->id)->whereDate('fecha', $datos['fecha']);

        if ($ignorarSesionId) {
            $queryDuplicada->where('id', '!=', $ignorarSesionId);
        }

        if ($queryDuplicada->exists()) {
            $errores['fecha'] = 'Ya existe una sesión para este curso en esa fecha.';
        }
    }

    private function validarDocenteOcupado(array $datos, ?int $ignorarSesionId, array &$errores): void
    {
        $queryDocente = Sesion::where('id_docente', $datos['id_docente'])
            ->whereDate('fecha', $datos['fecha'])
            ->whereTime('hora', $datos['hora']);

        if ($ignorarSesionId) {
            $queryDocente->where('id', '!=', $ignorarSesionId);
        }

        if ($queryDocente->exists()) {
            $docente = Docente::with('miembro')->find($datos['id_docente']);
            $nombre = $docente?->miembro?->nombre ?? 'El docente';
            $errores['id_docente'] = 'Ya existe una sesión de '.$nombre.' en esa fecha y hora.';
        }
    }

    private function validarHorarioCurso(array $datos, ?int $ignorarSesionId = null): void
    {
        $curso = Curso::find($datos['id_curso']);

        if (! $curso) {
            return;
        }

        $errores = [];
        $fecha = Carbon::parse($datos['fecha']);
        $horaInput = substr($datos['hora'], 0, 5);

        $this->validarRangoFechas($curso, $fecha, $errores);
        $this->validarDiaHorario($curso, $fecha, $horaInput, $errores);

        if (! $errores) {
            $this->validarSesionDuplicada($curso, $datos, $ignorarSesionId, $errores);
        }

        if (! $errores && ! empty($datos['id_docente'])) {
            $this->validarDocenteOcupado($datos, $ignorarSesionId, $errores);
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }
}
