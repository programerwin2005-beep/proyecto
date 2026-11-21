<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiembroRequest;
use App\Models\Miembro;
use Illuminate\Http\Request;

class MiembroController extends Controller
{
    public function index(Request $request)
    {
        $miembros = Miembro::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', '%'.$termino.'%')
                        ->orWhere('email', 'like', '%'.$termino.'%')
                        ->orWhere('telefono', 'like', '%'.$termino.'%');
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('miembros.index', compact('miembros'));
    }

    public function create()
    {
        return view('miembros.create');
    }

    public function store(MiembroRequest $request)
    {
        Miembro::create($request->validated());

        return redirect()->route('miembros.index')->with('success', 'Miembro registrado con éxito.');
    }

    public function show($id)
    {
        $miembro = Miembro::withTrashed()->findOrFail($id);

        return view('miembros.show', compact('miembro'));
    }

    public function edit($id)
    {
        $miembro = Miembro::withTrashed()->findOrFail($id);

        return view('miembros.edit', compact('miembro'));
    }

    public function update(MiembroRequest $request, $id)
    {
        $miembro = Miembro::withTrashed()->findOrFail($id);
        $miembro->update($request->validated());

        return redirect()->route('miembros.index')->with('success', 'Miembro actualizado con éxito.');
    }

    public function destroy($id)
    {
        $miembro = Miembro::findOrFail($id);
        $miembro->delete();

        return redirect()->route('miembros.index')->with('success', 'Miembro eliminado exitosamente.');
    }

}
