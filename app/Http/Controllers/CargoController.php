<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargoRequest;
use App\Models\Cargo;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index(Request $request)
    {
        // Paginación y soporte para ver elementos eliminados
        $cargos = Cargo::query()
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

        return view('cargos.index', compact('cargos'));
    }

    public function create()
    {
        return view('cargos.create');
    }

    public function store(CargoRequest $request)
    {
        Cargo::create($request->validated());

        return redirect()->route('cargos.index')->with('success', 'Cargo creado con éxito.');
    }

    public function show($id)
    {
        $cargo = Cargo::withTrashed()->findOrFail($id);

        return view('cargos.show', compact('cargo'));
    }

    public function edit($id)
    {
        $cargo = Cargo::withTrashed()->findOrFail($id);

        return view('cargos.edit', compact('cargo'));
    }

    public function update(CargoRequest $request, $id)
    {
        $cargo = Cargo::withTrashed()->findOrFail($id);
        $cargo->update($request->validated());

        return redirect()->route('cargos.index')->with('success', 'Cargo actualizado con éxito.');
    }

    public function destroy($id)
    {
        $cargo = Cargo::findOrFail($id);
        $cargo->delete(); // Borrado lógico

        return redirect()->route('cargos.index')->with('success', 'Cargo eliminado exitosamente.');
    }

}
