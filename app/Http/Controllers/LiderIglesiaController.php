<?php

namespace App\Http\Controllers;

use App\Http\Requests\LiderIglesiaRequest;
use App\Models\Cargo;
use App\Models\LiderIglesia;

class LiderIglesiaController extends Controller
{
    public function index()
    {
        $lideres = LiderIglesia::with('cargo')->paginate(10);

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

        return redirect()->route('lideres.index')->with('success', 'Líder eliminado lógicamente.');
    }

}
