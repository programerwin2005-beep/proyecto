<?php

namespace App\Http\Controllers;

use App\Http\Requests\LiderIglesiaRequest;
use App\Models\Cargo;
use App\Models\LiderIglesia;
use Illuminate\Http\Request;

class LiderIglesiaController extends Controller
{
    public function index(Request $request)
    {
        $lideres = LiderIglesia::with('cargo')
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('telefono', 'like', '%'.$termino.'%')
                        ->orWhereHas('cargo', fn ($c) => $c->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('lideres.index', compact('lideres'));
    }

    public function create()
    {
        $cargos = Cargo::all();

        return view('lideres.create', compact('cargos'));
    }

    public function store(LiderIglesiaRequest $request)
    {
        LiderIglesia::create($request->validated());

        return redirect()->route('lideres.index')->with('success', 'Líder de iglesia registrado con éxito.');
    }

    public function show($id)
    {
        $lider = LiderIglesia::with('cargo')->withTrashed()->findOrFail($id);

        return view('lideres.show', compact('lider'));
    }

    public function edit($id)
    {
        $lider = LiderIglesia::withTrashed()->findOrFail($id);
        $cargos = Cargo::all();

        return view('lideres.edit', compact('lider', 'cargos'));
    }

    public function update(LiderIglesiaRequest $request, $id)
    {
        $lider = LiderIglesia::withTrashed()->findOrFail($id);
        $lider->update($request->validated());

        return redirect()->route('lideres.index')->with('success', 'Líder de iglesia actualizado con éxito.');
    }

    public function destroy($id)
    {
        $lider = LiderIglesia::findOrFail($id);
        $lider->delete();

        return redirect()->route('lideres.index')->with('success', 'Líder eliminado exitosamente.');
    }
}
