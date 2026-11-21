<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActividadRequest;
use App\Models\Actividad;
use App\Models\Ministerio;
use Illuminate\Http\Request;

class ActividadController extends Controller
{
    public function index(Request $request)
    {
        $actividades = Actividad::with('ministerio')
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('lugar', 'like', '%'.$termino.'%')
                        ->orWhereHas('ministerio', fn ($m) => $m->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderByDesc('fecha')
            ->paginate(10)
            ->withQueryString();

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

        return redirect()->route('actividades.index')->with('success', 'Actividad eliminada exitosamente.');
    }

}
