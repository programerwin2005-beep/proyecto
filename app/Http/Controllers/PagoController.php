<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Models\Inscripcion;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $pagos = Pago::query()
            ->with(['inscripcion.miembro', 'inscripcion.curso', 'registrador'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $termino = trim($request->input('q'));
                $query->whereHas('inscripcion', function ($sub) use ($termino) {
                    $sub->whereHas('miembro', fn ($m) => $m->where('nombre', 'like', '%'.$termino.'%'))
                        ->orWhereHas('curso', fn ($c) => $c->where('nombre', 'like', '%'.$termino.'%'));
                });
            })
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $totales = [
            'total' => (float) Pago::sum('monto'),
            'cantidad' => Pago::count(),
        ];

        return view('pagos.index', compact('pagos', 'totales'));
    }

    public function create(Request $request)
    {
        $inscripciones = Inscripcion::with(['miembro', 'curso'])
            ->whereHas('curso', fn ($q) => $q->where('tiene_pago', true))
            ->orderBy('fecha_inscripcion', 'desc')
            ->get()
            ->filter(fn ($inscripcion) => $inscripcion->saldo_pendiente > 0)
            ->values();

        $cursos = $inscripciones
            ->pluck('curso')
            ->filter()
            ->unique('id')
            ->sortBy('nombre')
            ->values();

        $seleccionada = $request->integer('inscripcion') ?: null;

        return view('pagos.create', compact('inscripciones', 'cursos', 'seleccionada'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'inscripcion_id' => ['required', 'exists:inscripciones,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ]);

        $inscripcion = Inscripcion::with('curso')->findOrFail($validated['inscripcion_id']);

        abort_unless($inscripcion->curso?->tiene_pago, 403, 'Este curso no tiene costo de inscripción.');

        if ((float) $validated['monto'] > $inscripcion->saldo_pendiente) {
            return back()
                ->withInput()
                ->withErrors(['monto' => 'El monto no puede superar el saldo pendiente de Bs '.number_format($inscripcion->saldo_pendiente, 2).'.']);
        }

        $datos = collect($validated)->except('inscripcion_id')->all();
        // La fecha del pago no se puede modificar: siempre es la del día.
        $datos['fecha_pago'] = now()->toDateString();

        Pago::registrar($inscripcion, $datos);

        return redirect()->route('pagos.index')
            ->with('success', 'Pago registrado con éxito.');
    }

    public function edit(Pago $pago)
    {
        $pago->load(['inscripcion.miembro', 'inscripcion.curso']);

        return view('pagos.edit', compact('pago'));
    }

    public function update(Request $request, Pago $pago)
    {
        $pago->load(['inscripcion.curso']);

        $maximo = $pago->inscripcion->saldo_pendiente + (float) $pago->monto;

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:'.$maximo],
            'fecha_pago' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ]);

        // La fecha original del pago no se modifica.
        $validated['fecha_pago'] = $pago->fecha_pago;

        DB::transaction(function () use ($pago, $validated) {
            $pago->update($validated);
            $pago->sincronizarIngreso();
        });

        return redirect()->route('pagos.index')
            ->with('success', 'Pago actualizado con éxito.');
    }

    public function destroy(Request $request, Pago $pago)
    {
        DB::transaction(function () use ($pago) {
            // Al eliminar el pago también se elimina su ingreso asociado.
            $pago->ingreso()->delete();
            $pago->delete();
        });

        return redirect()->back(302, [], route('pagos.index'))
            ->with('success', 'Pago e ingreso eliminados con éxito.');
    }
}
