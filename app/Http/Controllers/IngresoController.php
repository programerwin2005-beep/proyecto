<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngresoRequest;
use App\Enums\TipoIngreso;
use App\Models\Actividad;
use App\Models\CategoriaFinanciera;
use App\Models\Culto;
use App\Models\Egreso;
use App\Models\Ingreso;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    public function index(Request $request)
    {
        $ingresos = Ingreso::with(['actividad', 'culto', 'pago.inscripcion.curso'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('tipo', 'like', '%'.$termino.'%')
                        ->orWhere('metodo_pago', 'like', '%'.$termino.'%')
                        ->orWhereHas('actividad', fn ($a) => $a->where('nombre', 'like', '%'.$termino.'%'))
                        ->orWhereHas('culto', fn ($c) => $c->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $totales = [
            'ingresos' => Ingreso::total(),
            'egresos' => Egreso::sum('monto'),
        ];
        $totales['balance'] = $totales['ingresos'] - $totales['egresos'];

        return view('ingresos.index', compact('ingresos', 'totales'));
    }

    public function create()
    {
        $cultos = Culto::all();

        return view('ingresos.create', compact('cultos'));
    }

    public function createActividad()
    {
        $actividades = Actividad::orderByDesc('fecha')->orderByDesc('id')->get();

        return view('ingresos.create-actividad', compact('actividades'));
    }

    public function storeActividad(IngresoRequest $request)
    {
        $datos = $this->conCategoria($request->validated());
        $datos['tipo'] = TipoIngreso::Actividad->value;
        // El ingreso de actividad no se asocia a un culto.
        $datos['id_culto'] = null;
        $datos['fecha'] = now()->toDateString();

        Ingreso::create($datos);

        return redirect()->route('ingresos.index')->with('success', 'Ingreso de actividad registrado con éxito.');
    }

    public function store(IngresoRequest $request)
    {
        $datos = $this->conCategoria($request->validated());
        // El ingreso general no se asocia a actividades: existe el flujo
        // separado "Ingreso Actividad" para ese caso.
        $datos['id_actividad'] = null;
        // La fecha del ingreso no se puede modificar: siempre es la del día.
        $datos['fecha'] = now()->toDateString();

        Ingreso::create($datos);

        return redirect()->route('ingresos.index')->with('success', 'Ingreso registrado con éxito.');
    }

    public function show($id)
    {
        $ingreso = Ingreso::with(['actividad', 'culto', 'pago'])->withTrashed()->findOrFail($id);

        return view('ingresos.show', compact('ingreso'));
    }

    public function edit($id)
    {
        $ingreso = Ingreso::with(['pago'])->withTrashed()->findOrFail($id);

        if ($ingreso->pago_id) {
            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; modifícalo desde el módulo de Pagos.');
        }

        $actividades = Actividad::all();
        $cultos = Culto::all();

        return view('ingresos.edit', compact('ingreso', 'actividades', 'cultos'));
    }

    public function update(IngresoRequest $request, $id)
    {
        $ingreso = Ingreso::withTrashed()->findOrFail($id);

        if ($ingreso->pago_id) {
            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; modifícalo desde el módulo de Pagos.');
        }

        $datos = $this->conCategoria($request->validated());
        // La fecha original no se modifica.
        $datos['fecha'] = $ingreso->fecha;

        $ingreso->update($datos);

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

        if ($ingreso->pago_id) {
            $ingreso->loadMissing('pago.inscripcion.curso');

            if ($ingreso->pago?->inscripcion?->curso?->haFinalizado()) {
                return redirect()->route('ingresos.index')
                    ->with('error', 'No se puede eliminar este ingreso porque el curso del pago ya finalizó.');
            }

            return redirect()->route('ingresos.index')
                ->with('error', 'Este ingreso proviene de un pago de curso; elimínalo desde el módulo de Pagos.');
        }

        $ingreso->delete();

        return redirect()->route('ingresos.index')->with('success', 'Ingreso eliminado exitosamente.');
    }
}
