<?php

namespace App\Http\Controllers;

use App\Http\Requests\MinisterioRequest;
use App\Models\LiderIglesia;
use App\Models\Ministerio;

class MinisterioController extends Controller
{
    public function index()
    {
        $ministerios = Ministerio::with('lider.cargo')->paginate(10);

        return view('ministerios.index', compact('ministerios'));
    }

    public function create()
    {
        $lideres = LiderIglesia::with('cargo')->orderBy('nombre')->get();

        return view('ministerios.create', compact('lideres'));
    }

    public function store(MinisterioRequest $request)
    {
        Ministerio::create($request->validated());

        return redirect()->route('ministerios.index')->with('success', 'Ministerio creado con éxito.');
    }

    public function show($id)
    {
        $ministerio = Ministerio::with(['lider.cargo', 'miembros'])->withTrashed()->findOrFail($id);

        return view('ministerios.show', compact('ministerio'));
    }

    public function edit($id)
    {
        $ministerio = Ministerio::withTrashed()->findOrFail($id);
        $lideres = LiderIglesia::with('cargo')->orderBy('nombre')->get();

        return view('ministerios.edit', compact('ministerio', 'lideres'));
    }

    public function update(MinisterioRequest $request, $id)
    {
        $ministerio = Ministerio::withTrashed()->findOrFail($id);
        $ministerio->update($request->validated());

        return redirect()->route('ministerios.index')->with('success', 'Ministerio actualizado con éxito.');
    }

    public function destroy($id)
    {
        $ministerio = Ministerio::findOrFail($id);
        $ministerio->delete();

        return redirect()->route('ministerios.index')->with('success', 'Ministerio eliminado lógicamente.');
    }
}
