<?php

namespace App\Http\Controllers;

use App\Http\Requests\CursoRequest;
use App\Models\Curso;
use App\Models\Inscripcion;

class CursoController extends Controller
{
    public function index()
    {
        $cursos = Curso::paginate(10);

        return view('cursos.index', compact('cursos'));
    }

    public function create()
    {
        return view('cursos.create');
    }

    public function store(CursoRequest $request)
    {
        $data = $request->validated();
        $data['dias_semana'] = $data['dias_semana'] ?? [];
        Curso::create($data);

        return redirect()->route('cursos.index')->with('success', 'Curso creado con éxito.');
    }

    public function show($id)
    {
        $curso = Curso::withTrashed()->findOrFail($id);
        $curso->load(['docentes.miembro', 'sesiones']);

        $inscripciones = Inscripcion::with(['miembro', 'pagos' => fn ($q) => $q->withTrashed()])
            ->withSum(['pagos' => fn ($q) => $q->withTrashed()], 'monto')
            ->where('curso_id', $id)
            ->orderBy('fecha_inscripcion')
            ->get();

        return view('cursos.show', compact('curso', 'inscripciones'));
    }

    public function edit($id)
    {
        $curso = Curso::withTrashed()->findOrFail($id);

        return view('cursos.edit', compact('curso'));
    }

    public function update(CursoRequest $request, $id)
    {
        $curso = Curso::withTrashed()->findOrFail($id);
        $data = $request->validated();
        $data['dias_semana'] = $data['dias_semana'] ?? [];
        $curso->update($data);

        return redirect()->route('cursos.index')->with('success', 'Curso actualizado con éxito.');
    }

    public function destroy($id)
    {
        $curso = Curso::findOrFail($id);
        $curso->delete();

        return redirect()->route('cursos.index')->with('success', 'Curso eliminado lógicamente.');
    }

}
