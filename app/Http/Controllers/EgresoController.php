<?php

namespace App\Http\Controllers;

use App\Enums\TipoEgreso;
use App\Http\Requests\EgresoRequest;
use App\Models\CategoriaFinanciera;
use App\Models\Contrato;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EgresoController extends Controller
{
    public function saldoDisponible(?int $ignorarId = null): float
    {
        $totalIngresos = Ingreso::total();
        $totalEgresos = Egreso::sum('monto');

        if ($ignorarId) {
            $totalEgresos -= Egreso::withTrashed()->find($ignorarId)->monto ?? 0;
        }

        return $totalIngresos - $totalEgresos;
    }

    public function index(Request $request)
    {
        $egresos = Egreso::with('contrato')
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));

                $query->where(function ($sub) use ($termino) {
                    $sub->where('tipo_egreso', 'like', '%'.$termino.'%')
                        ->orWhere('responsable', 'like', '%'.$termino.'%')
                        ->orWhere('descripcion', 'like', '%'.$termino.'%')
                        ->orWhere('comprobante', 'like', '%'.$termino.'%');
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

        return view('egresos.index', compact('egresos', 'totales'));
    }

    public function create()
    {
        $lideres = LiderIglesia::with('cargo')->get()->sortBy('nombre')->values();
        $saldoDisponible = $this->saldoDisponible();

        return view('egresos.create', compact('lideres', 'saldoDisponible'));
    }

    public function store(EgresoRequest $request)
    {
        $saldo = $this->saldoDisponible();

        if ($request->monto > $saldo) {
            return back()->withErrors(['monto' => 'No hay saldo suficiente. Saldo disponible: Bs '.number_format($saldo, 2).'.'])
                ->withInput();
        }

        $datos = $this->conCategoria($request->validated());
        // Los pagos de contrato solo se registran desde «Pagar Contrato».
        unset($datos['id_contrato']);
        // La fecha del egreso no se puede modificar: siempre es la del día.
        $datos['fecha'] = now()->toDateString();

        Egreso::create($datos);

        return redirect()->route('egresos.index')->with('success', 'Egreso registrado con éxito.');
    }

    public function createPagoContrato()
    {
        $contratos = Contrato::with(['miembro', 'lider.cargo', 'egresos'])
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->get()
            ->reject(fn (Contrato $contrato) => $contrato->contratoCumplido() && ! $contrato->tieneMesesPendientes())
            ->values();

        // Solo se ofrecen meses con saldo pendiente; un mes pagado no vuelve
        // a aparecer para evitar pagos repetidos.
        $periodosPorContrato = $contratos->mapWithKeys(function (Contrato $contrato) {
            return [$contrato->id => $contrato->periodosPago()
                ->reject(fn (array $periodo) => $periodo['completo'])
                ->map(fn (array $periodo) => [
                    'inicio' => $periodo['inicio']->toDateString(),
                    'etiqueta' => $periodo['inicio']->format('d/m/Y').' al '.$periodo['fin']->format('d/m/Y'),
                    'restante' => $periodo['restante'],
                ])
                ->values()];
        });

        $saldoDisponible = $this->saldoDisponible();

        return view('egresos.pagar-contrato', compact('contratos', 'saldoDisponible', 'periodosPorContrato'));
    }

    public function storePagoContrato(Request $request)
    {
        $validated = $request->validate([
            'id_contrato' => ['required', 'exists:contratos,id'],
            'periodo_inicio' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'comprobante' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $saldo = $this->saldoDisponible();

        if ((float) $validated['monto'] > $saldo) {
            return back()->withErrors(['monto' => 'No hay saldo suficiente. Saldo disponible: Bs '.number_format($saldo, 2).'.'])
                ->withInput();
        }

        $egreso = DB::transaction(function () use ($validated) {
            $contrato = Contrato::with(['miembro', 'lider', 'egresos'])
                ->lockForUpdate()
                ->findOrFail($validated['id_contrato']);

            if ($contrato->contratoCumplido() && ! $contrato->tieneMesesPendientes()) {
                return back()->withErrors(['id_contrato' => 'El contrato ya cumplió su vigencia y todos sus meses están pagados.'])
                    ->withInput();
            }

            $periodo = $contrato->periodoPorInicio($validated['periodo_inicio']);

            if (! $periodo) {
                return back()->withErrors(['periodo_inicio' => 'El mes seleccionado no corresponde al contrato.'])
                    ->withInput();
            }

            if ($periodo['completo']) {
                return back()->withErrors(['periodo_inicio' => 'El mes seleccionado ya está pagado; no se puede repetir el pago.'])
                    ->withInput();
            }

            $monto = round((float) $validated['monto'], 2);

            if ($monto > $periodo['restante']) {
                return back()->withErrors(['monto' => 'El monto supera lo que falta del mes. Pendiente: Bs '.number_format($periodo['restante'], 2).'.'])
                    ->withInput();
            }

            $responsable = $contrato->miembro?->nombre
                ?? $contrato->lider?->nombre
                ?? 'Contrato #'.$contrato->id;

            $datos = [
                'id_contrato' => $contrato->id,
                'monto' => $monto,
                'fecha' => now()->toDateString(),
                'periodo_inicio' => $periodo['inicio']->toDateString(),
                'tipo_egreso' => TipoEgreso::Salarios->value,
                'responsable' => $responsable,
                'comprobante' => $validated['comprobante'] ?? null,
                'descripcion' => ($validated['descripcion'] ?? null) ?: 'Pago de contrato #'.$contrato->id.' - '.$responsable,
            ];

            return Egreso::create($this->conCategoria($datos));
        });

        if ($egreso instanceof \Illuminate\Http\RedirectResponse) {
            return $egreso;
        }

        return redirect()->route('egresos.index')->with('success', 'Pago de contrato registrado con éxito.');
    }

    public function show($id)
    {
        $egreso = Egreso::with('contrato')->withTrashed()->findOrFail($id);

        return view('egresos.show', compact('egreso'));
    }

    public function edit($id)
    {
        $egreso = Egreso::withTrashed()->findOrFail($id);
        $lideres = LiderIglesia::with('cargo')->get()->sortBy('nombre')->values();
        $saldoDisponible = $this->saldoDisponible($egreso->id);

        return view('egresos.edit', compact('egreso', 'lideres', 'saldoDisponible'));
    }

    public function update(EgresoRequest $request, $id)
    {
        $egreso = Egreso::withTrashed()->findOrFail($id);
        $saldo = $this->saldoDisponible($egreso->id);

        if ($request->monto > $saldo) {
            return back()->withErrors(['monto' => 'No hay saldo suficiente. Saldo disponible: Bs '.number_format($saldo, 2).'.'])
                ->withInput();
        }

        $datos = $this->conCategoria($request->validated());
        // La relación con contrato no se modifica desde el egreso normal.
        unset($datos['id_contrato']);
        // La fecha original no se modifica.
        $datos['fecha'] = $egreso->fecha;
        $egreso->update($datos);

        return redirect()->route('egresos.index')->with('success', 'Egreso actualizado con éxito.');
    }

    /**
     * Completa `categoria_id` a partir del tipo cuando el formulario no lo envía.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conCategoria(array $datos): array
    {
        if (empty($datos['categoria_id']) && ! empty($datos['tipo_egreso'])) {
            $datos['categoria_id'] = CategoriaFinanciera::idPara(
                $datos['tipo_egreso'],
                CategoriaFinanciera::NATURALEZA_EGRESO
            );
        }

        return $datos;
    }

    public function destroy($id)
    {
        $egreso = Egreso::findOrFail($id);
        $egreso->delete();

        return redirect()->route('egresos.index')->with('success', 'Egreso eliminado exitosamente.');
    }
}
