<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiembroRequest;
use App\Models\Miembro;

class MiembroController extends Controller
{
    public function index()
    {
        $miembros = Miembro::paginate(10);

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

        return redirect()->route('miembros.index')->with('success', 'Miembro eliminado lógicamente.');
    }

}
