<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContratoRequest;
use App\Models\Contrato;
use App\Models\Egreso;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $expresionVigencia = "CASE
            WHEN contratos.deleted_at IS NOT NULL THEN 'eliminado'
            WHEN contratos.fecha_inicio > CURDATE() THEN 'futuro'
            WHEN contratos.fecha_fin IS NOT NULL AND contratos.fecha_fin < CURDATE() THEN 'vencido'
            WHEN contratos.fecha_fin IS NOT NULL AND contratos.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'por_vencer'
            ELSE 'vigente' END";

        $contratos = Contrato::with(['miembro', 'lider.cargo'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('miembro', fn ($m) => $m->where('nombre', 'like', '%'.$request->q.'%')))
            ->when($request->filled('tipo_compensacion'), fn ($q) => $q->where('tipo_compensacion', $request->tipo_compensacion))
            ->when($request->filled('estado'), fn ($q) => $q->whereRaw("$expresionVigencia = ?", [$request->estado]))
            ->orderByDesc('fecha_inicio')
            ->paginate(10)
            ->withQueryString();

        return view('contratos.index', compact('contratos'));
    }

    public function create()
    {
        $miembros = Miembro::where('estado', 'Activo')->orderBy('nombre')->get();
        $lideres = LiderIglesia::where('activo', true)->with('cargo')->orderBy('nombre')->get();
        $tiposCompensacion = Contrato::tiposCompensacion();

        return view('contratos.create', compact('miembros', 'lideres', 'tiposCompensacion'));
    }

    public function store(ContratoRequest $request)
    {
        Contrato::create($request->validated());

        return redirect()->route('contratos.index')->with('success', 'Contrato creado con éxito.');
    }

    public function show($id)
    {
        $contrato = Contrato::with(['miembro', 'lider.cargo', 'egresos' => fn ($q) => $q->withTrashed()])->withTrashed()->findOrFail($id);
        $totalPagado = (float) Egreso::where('id_contrato', $contrato->id)->sum('monto');

        return view('contratos.show', compact('contrato', 'totalPagado'));
    }

    public function edit($id)
    {
        $contrato = Contrato::withTrashed()->findOrFail($id);
        $miembros = Miembro::where('estado', 'Activo')->orderBy('nombre')->get();
        $lideres = LiderIglesia::where('activo', true)->with('cargo')->orderBy('nombre')->get();
        $tiposCompensacion = Contrato::tiposCompensacion();

        return view('contratos.edit', compact('contrato', 'miembros', 'lideres', 'tiposCompensacion'));
    }

    public function update(ContratoRequest $request, $id)
    {
        $contrato = Contrato::withTrashed()->findOrFail($id);
        $contrato->update($request->validated());

        return redirect()->route('contratos.index')->with('success', 'Contrato actualizado con éxito.');
    }

    public function destroy($id)
    {
        $contrato = Contrato::findOrFail($id);
        $contrato->delete();

        return redirect()->route('contratos.index')->with('success', 'Contrato eliminado lógicamente.');
    }


    public function renovar($id)
    {
        $contrato = Contrato::withTrashed()->findOrFail($id);

        if (! $contrato->vigente) {
            $nuevoInicio = now()->toDateString();
        } else {
            $nuevoInicio = Carbon::parse($contrato->fecha_fin ?? now())->addDay()->toDateString();
            $contrato->update(['fecha_fin' => now()->toDateString()]);
        }

        return redirect()->route('contratos.create')->withInput([
            'id_miembro' => $contrato->id_miembro,
            'id_lider_iglesia' => $contrato->id_lider_iglesia,
            'tipo_compensacion' => $contrato->tipo_compensacion,
            'salario' => $contrato->salario,
            'fecha_inicio' => $nuevoInicio,
        ])->with('success', 'Contrato anterior cerrado. Revise los datos y guarde la renovación.');
    }
}
