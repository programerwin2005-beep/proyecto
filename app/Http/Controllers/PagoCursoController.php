<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Inscripcion;
use App\Models\PagoCurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoCursoController extends Controller
{
    public function create(Inscripcion $inscripcion)
    {
        abort_unless($inscripcion->curso->tiene_pago, 403, 'Este curso no tiene costo de inscripción.');

        if ($inscripcion->saldo_pendiente <= 0) {
            return redirect()->route('inscripciones.gestion', $inscripcion->curso_id)
                ->with('error', 'Esta inscripción ya está pagada por completo.');
        }

        $inscripcion->load(['miembro', 'curso']);

        return view('pagos.create', compact('inscripcion'));
    }

    public function store(Request $request, Inscripcion $inscripcion)
    {
        abort_unless($inscripcion->curso->tiene_pago, 403, 'Este curso no tiene costo de inscripción.');

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:'.$inscripcion->saldo_pendiente],
            'fecha_pago' => ['required', 'date'],
            'metodo_pago' => ['required', 'string', 'in:Efectivo,Transferencia,Tarjeta'],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $inscripcion) {
            $pago = $inscripcion->pagos()->create(
                $validated + ['registrado_por' => auth()->id()]
            );

            // El pago aparece automáticamente en Ingresos.
            // Opción A: no se copian monto, fecha ni método; se leen del pago con COALESCE.
            $pago->ingreso()->create([
                'tipo' => 'Inscripción Curso: '.$inscripcion->curso->nombre,
            ]);
        });

        return redirect()->route('inscripciones.gestion', $inscripcion->curso_id)
            ->with('success', 'Pago registrado con éxito.');
    }

    public function destroy($id)
    {
        $pago = PagoCurso::findOrFail($id);
        $cursoId = $pago->inscripcion->curso_id;

        DB::transaction(function () use ($pago) {
            $pago->ingreso()->delete();
            $pago->delete();
        });

        return redirect()->route('inscripciones.gestion', $cursoId)
            ->with('success', 'Pago eliminado lógicamente.');
    }

}
