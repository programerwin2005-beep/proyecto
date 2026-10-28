<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::paginate(10);

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|string|in:Activo,Inactivo',
            'nivel_acceso' => 'required|integer|min:1',
            'guard_name' => 'nullable|string|max:255',
        ]);

        if (empty($validated['guard_name'])) {
            $validated['guard_name'] = 'web';
        }

        Role::create($validated);

        return redirect()->route('roles.index')->with('success', 'Rol creado con éxito.');
    }

    public function edit($id)
    {
        $role = Role::withTrashed()->findOrFail($id);

        return view('roles.edit', compact('role'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,nombre,'.$role->id,
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|string|in:Activo,Inactivo',
            'nivel_acceso' => 'required|integer|min:1',
            'guard_name' => 'nullable|string|max:255',
        ]);

        if (empty($validated['guard_name'])) {
            $validated['guard_name'] = 'web';
        }

        $role->update($validated);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado con éxito.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado lógicamente.');
    }


    // rol_usuario view: list users and roles for assignment
    public function userRoles()
    {
        $users = User::with('roles')->paginate(10);
        $roles = Role::all();

        return view('roles.user_roles', compact('users', 'roles'));
    }

    // formulario de asignación de roles para un usuario específico
    public function userRolesEdit($userId)
    {
        $user = User::with('roles')->findOrFail($userId);
        $roles = Role::all();

        return view('roles.user_roles_edit', compact('user', 'roles'));
    }

    // handle assignment of roles to a user
    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->roles()->sync($request->input('roles', []));

        return redirect()->route('roles.user')->with('success', 'Roles actualizados con éxito para el usuario.');
    }

    public function clearUserRoles($userId)
    {
        $user = User::findOrFail($userId);
        $user->roles()->detach();

        return redirect()->route('roles.user')->with('success', 'Relación rol-usuario eliminada con éxito.');
    }
}
