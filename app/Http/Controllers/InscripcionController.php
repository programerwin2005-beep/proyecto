<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\PagoCurso;
use Illuminate\Http\Request;

class InscripcionController extends Controller
{
    public function index(Request $request)
    {
        $cursos = Curso::query()
            ->withCount(['inscripciones' => fn ($q) => $q->whereNull('inscripciones.deleted_at')])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termino = trim($request->input('q'));
                $q->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('descripcion', 'like', '%'.$termino.'%');
                });
            })
            ->when($request->input('tipo') === 'con_pago', fn ($q) => $q->where('tiene_pago', true))
            ->when($request->input('tipo') === 'gratuito', fn ($q) => $q->where('tiene_pago', false))
            ->when($request->input('cupo') === 'disponible', fn ($q) => $q->havingRaw('inscripciones_count < cupo_max'))
            ->when($request->input('cupo') === 'lleno', fn ($q) => $q->havingRaw('inscripciones_count >= cupo_max'))
            ->orderBy('f_inicio', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('inscripciones.index', compact('cursos'));
    }

    public function gestion($id)
    {
        $curso = Curso::withTrashed()->findOrFail($id);

        $inscripciones = Inscripcion::with(['miembro', 'pagos' => fn ($q) => $q->withTrashed()])
            ->withSum(['pagos' => fn ($q) => $q->withTrashed()], 'monto')
            ->where('curso_id', $id)
            ->orderBy('fecha_inscripcion')
            ->get();

        $miembrosDisponibles = Inscripcion::getMiembrosDisponibles($curso->id);

        $totalPagado = PagoCurso::whereIn('inscripcion_id', $inscripciones->pluck('id'))->sum('monto');
        $esperado = (float) $curso->monto_inscripcion * $inscripciones->whereNull('deleted_at')->count();

        return view('inscripciones.gestion', compact('curso', 'inscripciones', 'miembrosDisponibles', 'totalPagado', 'esperado'));
    }

    public function store(Request $request, Curso $curso)
    {
        if ($curso->trashed()) {
            abort(404);
        }

        $validated = $request->validate([
            'miembro_id' => ['required', 'exists:miembros,id'],
            'fecha_inscripcion' => ['required', 'date'],
        ]);

        $existente = Inscripcion::withTrashed()
            ->where('miembro_id', $validated['miembro_id'])
            ->where('curso_id', $curso->id)
            ->first();

        if ($existente) {
            $mensaje = $existente->trashed()
                ? 'Ya existe una inscripción eliminada para este miembro en este curso.'
                : 'El miembro ya está inscrito en este curso.';

            return back()->with('error', $mensaje);
        }

        $inscritos = Inscripcion::where('curso_id', $curso->id)->count();

        if ($inscritos >= $curso->cupo_max) {
            return back()->with('error', 'El curso alcanzó su cupo máximo de '.$curso->cupo_max.' inscritos.');
        }

        Inscripcion::create($validated + [
            'curso_id' => $curso->id,
            'estado' => 'Activo',
        ]);

        return redirect()->route('inscripciones.gestion', $curso->id)
            ->with('success', 'Miembro inscrito con éxito.');
    }

    public function edit($id)
    {
        $inscripcion = Inscripcion::withTrashed()
            ->with(['miembro', 'curso'])
            ->findOrFail($id);

        return view('inscripciones.edit', compact('inscripcion'));
    }

    public function update(Request $request, $id)
    {
        $inscripcion = Inscripcion::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'fecha_inscripcion' => ['required', 'date'],
            'estado' => ['required', 'string', 'max:50', 'in:Activo,Retirado,Completado'],
        ]);

        $inscripcion->update($validated);

        return redirect()->route('inscripciones.gestion', $inscripcion->curso_id)
            ->with('success', 'Inscripción actualizada con éxito.');
    }

    public function destroy($id)
    {
        $inscripcion = Inscripcion::findOrFail($id);
        $cursoId = $inscripcion->curso_id;
        $inscripcion->delete();

        return redirect()->route('inscripciones.gestion', $cursoId)
            ->with('success', 'Inscripción eliminada lógicamente.');
    }

}
