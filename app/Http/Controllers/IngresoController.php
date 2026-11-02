<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngresoRequest;
use App\Models\Actividad;
use App\Models\CategoriaFinanciera;
use App\Models\Culto;
use App\Models\Egreso;
use App\Models\Ingreso;

class IngresoController extends Controller
{
    public function index()
    {
        $ingresos = Ingreso::with(['actividad', 'culto', 'pagoCurso'])->paginate(10);

        $totales = [
            'ingresos' => Ingreso::total(),
            'egresos' => Egreso::sum('monto'),
        ];
        $totales['balance'] = $totales['ingresos'] - $totales['egresos'];

        return view('ingresos.index', compact('ingresos', 'totales'));
    }

    public function create()
    {
        $actividades = Actividad::all();
        $cultos = Culto::all();

        return view('ingresos.create', compact('actividades', 'cultos'));
    }

    public function store(IngresoRequest $request)
    {
        Ingreso::create($this->conCategoria($request->validated()));

        return redirect()->route('ingresos.index')->with('success', 'Ingreso registrado con éxito.');
    }

    public function show($id)
    {
        $ingreso = Ingreso::with(['actividad', 'culto', 'pagoCurso'])->withTrashed()->findOrFail($id);

        return view('ingresos.show', compact('ingreso'));
    }

    public function edit($id)
    {
        $ingreso = Ingreso::with(['pagoCurso'])->withTrashed()->findOrFail($id);

        if ($ingreso->pago_curso_id) {
            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; modifícalo desde el módulo de inscripciones.');
        }

        $actividades = Actividad::all();
        $cultos = Culto::all();

        return view('ingresos.edit', compact('ingreso', 'actividades', 'cultos'));
    }

    public function update(IngresoRequest $request, $id)
    {
        $ingreso = Ingreso::withTrashed()->findOrFail($id);

        if ($ingreso->pago_curso_id) {
            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; modifícalo desde el módulo de inscripciones.');
        }

        $ingreso->update($this->conCategoria($request->validated()));

        return redirect()->route('ingresos.index')->with('success', 'Ingreso actualizado con éxito.');
    }

    /**
     * Completa `categoria_id` a partir del tipo cuando el formulario no lo envía.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conCategoria(array $datos): array
    {
        if (empty($datos['categoria_id']) && ! empty($datos['tipo'])) {
            $datos['categoria_id'] = CategoriaFinanciera::idPara(
                $datos['tipo'],
                CategoriaFinanciera::NATURALEZA_INGRESO
            );
        }

        return $datos;
    }

    public function destroy($id)
    {
        $ingreso = Ingreso::findOrFail($id);

        if ($ingreso->pago_curso_id) {
            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; elimínalo desde el módulo de inscripciones.');
        }

        $ingreso->delete();

        return redirect()->route('ingresos.index')->with('success', 'Ingreso eliminado lógicamente.');
    }
}
