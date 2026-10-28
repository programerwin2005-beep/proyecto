<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DocenteController extends Controller
{
    public function index()
    {
        $docentes = Docente::with(['miembro', 'user'])->paginate(10);

        return view('docentes.index', compact('docentes'));
    }

    public function create()
    {
        $miembros = Miembro::whereDoesntHave('docente', fn ($q) => $q->withTrashed())->get();

        return view('docentes.create', compact('miembros'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_miembro' => [
                'required',
                'exists:miembros,id',
                Rule::unique('docentes', 'id_miembro')->whereNull('deleted_at'),
            ],
            'especialidad' => 'required|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        $docente = Docente::withTrashed()
            ->where('id_miembro', $validated['id_miembro'])
            ->first();

        if ($docente) {
            $mensaje = $docente->trashed()
                ? 'Ya existe un docente eliminado para este miembro.'
                : 'El miembro ya está registrado como docente.';

            return back()->with('error', $mensaje)->withInput();
        }

        Docente::create($validated);

        return redirect()->route('docentes.index')->with('success', 'Docente registrado con éxito.');
    }

    public function show($id)
    {
        $docente = Docente::with(['miembro', 'user', 'cursos'])->withTrashed()->findOrFail($id);

        return view('docentes.show', compact('docente'));
    }

    public function edit($id)
    {
        $docente = Docente::withTrashed()->findOrFail($id);
        $miembros = Miembro::all();

        return view('docentes.edit', compact('docente', 'miembros'));
    }

    public function update(Request $request, $id)
    {
        $docente = Docente::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'id_miembro' => 'required|exists:miembros,id|unique:docentes,id_miembro,'.$docente->id,
            'especialidad' => 'required|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        $docente->update($validated);

        return redirect()->route('docentes.index')->with('success', 'Docente actualizado con éxito.');
    }

    /**
     * Formulario para crear el usuario de acceso del docente.
     */
    public function acceso(Docente $docente)
    {
        $docente->load('miembro', 'user');

        if ($docente->user_id) {
            return redirect()->route('docentes.show', $docente->id)
                ->with('error', 'Este docente ya tiene un acceso al sistema.');
        }

        return view('docentes.acceso', compact('docente'));
    }

    /**
     * Crea el usuario con rol Docente y lo vincula al docente.
     */
    public function guardarAcceso(Request $request, Docente $docente)
    {
        if ($docente->user_id) {
            return redirect()->route('docentes.show', $docente->id)
                ->with('error', 'Este docente ya tiene un acceso al sistema.');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated, $docente) {
            $user = User::create([
                'name' => $docente->miembro->nombre,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::where('nombre', 'Docente')->firstOrFail();
            $user->roles()->attach($role->id);

            $docente->update(['user_id' => $user->id]);
        });

        return redirect()->route('docentes.show', $docente->id)
            ->with('success', 'Acceso del docente creado con éxito.');
    }

    public function destroy($id)
    {
        $docente = Docente::with('user')->findOrFail($id);

        DB::transaction(function () use ($docente) {
            // El usuario del docente es exclusivo para la asistencia: se le
            // retira el rol y, si no tiene otros roles, se elimina lógicamente.
            if ($docente->user) {
                $role = Role::where('nombre', 'Docente')->first();

                if ($role) {
                    $docente->user->roles()->detach($role->id);
                }

                if ($docente->user->roles()->count() === 0) {
                    $docente->user->delete();
                }
            }

            $docente->delete();
        });

        return redirect()->route('docentes.index')->with('success', 'Docente eliminado lógicamente.');
    }

}
