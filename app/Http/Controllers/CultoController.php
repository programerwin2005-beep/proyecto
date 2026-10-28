<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\LiderIglesia;
use Illuminate\Http\Request;

class CultoController extends Controller
{
    public function index()
    {
        $cultos = Culto::with('liderIglesia')->paginate(10);

        return view('cultos.index', compact('cultos'));
    }

    public function create()
    {
        $lideres = LiderIglesia::all();

        return view('cultos.create', compact('lideres'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_lider_iglesia' => 'required|exists:lideres_iglesia,id',
            'nombre' => 'required|string|max:255',
            'dia_semana' => 'required|string|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'hora' => 'required|date_format:H:i',
            'descripcion' => 'nullable|string',
        ]);

        Culto::create($validated);

        return redirect()->route('cultos.index')->with('success', 'Culto registrado con éxito.');
    }

    public function show($id)
    {
        $culto = Culto::with(['liderIglesia', 'ingresos'])->withTrashed()->findOrFail($id);

        return view('cultos.show', compact('culto'));
    }

    public function edit($id)
    {
        $culto = Culto::withTrashed()->findOrFail($id);
        $lideres = LiderIglesia::all();

        return view('cultos.edit', compact('culto', 'lideres'));
    }

    public function update(Request $request, $id)
    {
        $culto = Culto::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'id_lider_iglesia' => 'required|exists:lideres_iglesia,id',
            'nombre' => 'required|string|max:255',
            'dia_semana' => 'required|string|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'hora' => 'required|date_format:H:i',
            'descripcion' => 'nullable|string',
        ]);

        $culto->update($validated);

        return redirect()->route('cultos.index')->with('success', 'Culto actualizado con éxito.');
    }

    public function destroy($id)
    {
        $culto = Culto::findOrFail($id);
        $culto->delete();

        return redirect()->route('cultos.index')->with('success', 'Culto eliminado lógicamente.');
    }

}
