<?php

namespace App\Http\Controllers;

use App\Enums\DiaSemana;
use App\Models\Culto;
use App\Rules\SoloLetras;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CultoController extends Controller
{
    public function index(Request $request)
    {
        $cultos = Culto::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('descripcion', 'like', '%'.$termino.'%');
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('cultos.index', compact('cultos'));
    }

    public function create()
    {
        return view('cultos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'dia_semana' => ['required', Rule::enum(DiaSemana::class)],
            'hora' => 'required|date_format:H:i',
            'descripcion' => 'nullable|string',
        ]);

        Culto::create($validated);

        return redirect()->route('cultos.index')->with('success', 'Culto registrado con éxito.');
    }

    public function show($id)
    {
        $culto = Culto::with('ingresos')->withTrashed()->findOrFail($id);

        return view('cultos.show', compact('culto'));
    }

    public function edit($id)
    {
        $culto = Culto::withTrashed()->findOrFail($id);

        return view('cultos.edit', compact('culto'));
    }

    public function update(Request $request, $id)
    {
        $culto = Culto::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'dia_semana' => ['required', Rule::enum(DiaSemana::class)],
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

        return redirect()->route('cultos.index')->with('success', 'Culto eliminado exitosamente.');
    }
}
