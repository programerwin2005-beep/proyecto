<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActividadRequest;
use App\Models\Actividad;
use App\Models\Ministerio;

class ActividadController extends Controller
{
    public function index()
    {
        $actividades = Actividad::with('ministerio')->paginate(10);

        return view('actividades.index', compact('actividades'));
    }

    public function create()
    {
        $ministerios = Ministerio::all();

        return view('actividades.create', compact('ministerios'));
    }

    public function store(ActividadRequest $request)
    {
        Actividad::create($request->validated());

        return redirect()->route('actividades.index')->with('success', 'Actividad registrada con éxito.');
    }

    public function show($id)
    {
        $actividad = Actividad::with(['ministerio', 'seguimientos'])->withTrashed()->findOrFail($id);

        return view('actividades.show', compact('actividad'));
    }

    public function edit($id)
    {
        $actividad = Actividad::withTrashed()->findOrFail($id);
        $ministerios = Ministerio::all();

        return view('actividades.edit', compact('actividad', 'ministerios'));
    }

    public function update(ActividadRequest $request, $id)
    {
        $actividad = Actividad::withTrashed()->findOrFail($id);
        $actividad->update($request->validated());

        return redirect()->route('actividades.index')->with('success', 'Actividad actualizada con éxito.');
    }

    public function destroy($id)
    {
        $actividad = Actividad::findOrFail($id);
        $actividad->delete();

        return redirect()->route('actividades.index')->with('success', 'Actividad eliminada lógicamente.');
    }

}
