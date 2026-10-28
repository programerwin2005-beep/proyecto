<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\SeguimientoActividad;
use Illuminate\Http\Request;

class SeguimientoActividadController extends Controller
{
    public function index()
    {
        $seguimientos = SeguimientoActividad::with('actividad')->paginate(10);

        return view('seguimiento.index', compact('seguimientos'));
    }

    public function create()
    {
        $actividades = Actividad::all();

        return view('seguimiento.create', compact('actividades'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_actividad' => 'required|exists:actividades,id',
            'fecha_registro' => 'required|date',
            'observacion' => 'nullable|string',
            'responsable' => 'required|string|max:255',
            'porcentaje_avance' => 'required|numeric|min:0|max:100',
            'estado' => 'required|string|in:En Progreso,Completado,Detenido',
        ]);

        SeguimientoActividad::create($validated);

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento registrado con éxito.');
    }

    public function show($id)
    {
        $seguimiento = SeguimientoActividad::with('actividad')->withTrashed()->findOrFail($id);

        return view('seguimiento.show', compact('seguimiento'));
    }

    public function edit($id)
    {
        $seguimiento = SeguimientoActividad::withTrashed()->findOrFail($id);
        $actividades = Actividad::all();

        return view('seguimiento.edit', compact('seguimiento', 'actividades'));
    }

    public function update(Request $request, $id)
    {
        $seguimiento = SeguimientoActividad::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'id_actividad' => 'required|exists:actividades,id',
            'fecha_registro' => 'required|date',
            'observacion' => 'nullable|string',
            'responsable' => 'required|string|max:255',
            'porcentaje_avance' => 'required|numeric|min:0|max:100',
            'estado' => 'required|string|in:En Progreso,Completado,Detenido',
        ]);

        $seguimiento->update($validated);

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento actualizado con éxito.');
    }

    public function destroy($id)
    {
        $seguimiento = SeguimientoActividad::findOrFail($id);
        $seguimiento->delete();

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento eliminado lógicamente.');
    }

}
