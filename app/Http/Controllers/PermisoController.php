<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('permisos')->orderBy('nombre')->paginate(10);

        return view('permisos.index', compact('roles'));
    }

    public function edit($id)
    {
        $role = Role::withTrashed()->findOrFail($id);
        $categorias = config('permisos.categorias', []);
        $acciones = config('permisos.acciones', []);
        $actuales = $role->permisos()->get()->keyBy('modulo');

        return view('permisos.edit', compact('role', 'categorias', 'acciones', 'actuales'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::withTrashed()->findOrFail($id);

        $request->validate([
            'permisos' => 'nullable|array',
            'permisos.*' => 'array',
            'permisos.*.*' => 'nullable|boolean',
        ]);

        $data = $request->input('permisos', []);
        $modulos = collect(config('permisos.categorias', []))->flatMap(fn ($grupo) => $grupo);

        foreach ($modulos as $modulo => $meta) {
            $valores = $data[$modulo] ?? [];
            $permisos = [];
            foreach (array_keys(config('permisos.acciones', [])) as $accion) {
                $permisos[$accion] = in_array($accion, $meta['acciones'], true)
                    && ! empty($valores[$accion]);
            }

            $role->permisos()->updateOrCreate(
                ['modulo' => $modulo],
                $permisos
            );
        }

        return redirect()->route('permisos.edit', $role->id)
            ->with('success', 'Permisos actualizados para el rol '.$role->nombre.'.');
    }
}
