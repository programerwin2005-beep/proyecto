<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSeguimiento;
use App\Models\Actividad;
use App\Models\SeguimientoActividad;
use App\Rules\ResponsableDeActividad;
use App\Rules\SoloLetras;
use App\Services\ActividadTareasService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeguimientoActividadController extends Controller
{
    public function index(Request $request)
    {
        $seguimientos = SeguimientoActividad::with('actividad')
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre_tarea', 'like', '%'.$termino.'%')
                        ->orWhere('responsable', 'like', '%'.$termino.'%')
                        ->orWhere('observacion', 'like', '%'.$termino.'%')
                        ->orWhereHas('actividad', fn ($a) => $a->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderByDesc('fecha_registro')
            ->paginate(10)
            ->withQueryString();

        return view('seguimiento.index', compact('seguimientos'));
    }

    public function create()
    {
        $actividades = Actividad::with(['ministerio.lider', 'ministerio.miembros'])->get();

        return view('seguimiento.create', compact('actividades'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_tarea' => 'required|string|max:255',
            'id_actividad' => 'required|exists:actividades,id',
            'fecha_registro' => 'required|date',
            'observacion' => 'nullable|string',
            'responsable' => [
                'required', 'string', 'max:255', new SoloLetras,
                new ResponsableDeActividad($request->integer('id_actividad')),
            ],
            'estado' => ['required', Rule::enum(EstadoSeguimiento::class)],
        ]);

        app(ActividadTareasService::class)->guardarTarea($validated);

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento registrado con éxito.');
    }

    public function show($id)
    {
        $seguimiento = SeguimientoActividad::with('actividad')->withTrashed()->findOrFail($id);

        return view('seguimiento.show', compact('seguimiento'));
    }

    public function edit($id)
    {
        $seguimiento = SeguimientoActividad::findOrFail($id);
        $actividades = Actividad::with(['ministerio.lider', 'ministerio.miembros'])->get();

        return view('seguimiento.edit', compact('seguimiento', 'actividades'));
    }

    public function update(Request $request, $id)
    {
        $seguimiento = SeguimientoActividad::findOrFail($id);
        $mismaActividad = $request->integer('id_actividad') === (int) $seguimiento->id_actividad;

        $validated = $request->validate([
            'nombre_tarea' => 'required|string|max:255',
            'id_actividad' => 'required|exists:actividades,id',
            'fecha_registro' => 'required|date',
            'observacion' => 'nullable|string',
            'responsable' => [
                'required', 'string', 'max:255', new SoloLetras,
                new ResponsableDeActividad(
                    $request->integer('id_actividad'),
                    $mismaActividad ? $seguimiento->responsable : null,
                ),
            ],
            'estado' => ['required', Rule::enum(EstadoSeguimiento::class)],
        ]);

        app(ActividadTareasService::class)->guardarTarea($validated, (int) $seguimiento->id);

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento actualizado con éxito.');
    }

    public function destroy($id)
    {
        app(ActividadTareasService::class)->eliminarTarea((int) $id);

        return redirect()->route('seguimiento.index')->with('success', 'Seguimiento eliminado exitosamente.');
    }

    public function completar(Request $request, int $id)
    {
        $datos = $request->validate(['completada' => ['required', 'boolean']]);
        app(ActividadTareasService::class)->completarTarea($id, (bool) $datos['completada']);

        return back()->with('success', 'Estado de la tarea actualizado.');
    }
}
