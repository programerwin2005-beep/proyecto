<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoMiembro;
use App\Enums\MetodoPago;
use App\Enums\TipoEgreso;
use App\Enums\TipoIngreso;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\Miembro;
use App\Models\Ministerio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReporteController extends Controller
{
    public function index()
    {
        return view('reportes.index');
    }

    public function miembros(Request $request)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', Rule::enum(EstadoMiembro::class)],
            'desde' => ['nullable', 'date'],
            'hasta' => array_filter(['nullable', 'date', $request->filled('desde') ? 'after_or_equal:desde' : null]),
            'curso_id' => ['nullable', 'integer', Rule::exists('cursos', 'id')->whereNull('deleted_at')],
            'contratos' => ['nullable', Rule::in(['con', 'sin'])],
            'ministerio_id' => ['nullable', 'integer', Rule::exists('ministerios', 'id')->whereNull('deleted_at')],
            'ministerios' => ['nullable', Rule::in(['con', 'sin'])],
        ]);
        $estado = $filtros['estado'] ?? '';
        $desde = $filtros['desde'] ?? '';
        $hasta = $filtros['hasta'] ?? '';
        $hoy = today()->toDateString();
        $ministeriosVigentes = fn ($q) => $q
            ->where('miembro_ministerio.fecha_inicio', '<=', $hoy)
            ->where(fn ($fechas) => $fechas->whereNull('miembro_ministerio.fecha_fin')->orWhere('miembro_ministerio.fecha_fin', '>=', $hoy));

        $query = Miembro::with([
            'cursos',
            'contratos',
            'ministerios' => $ministeriosVigentes,
        ])
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $q->where(fn ($busqueda) => $busqueda->where('nombre', 'like', '%'.trim($texto).'%')
                    ->orWhere('email', 'like', '%'.trim($texto).'%')
                    ->orWhere('telefono', 'like', '%'.trim($texto).'%'));
            })
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->whereHas('cursos', fn ($curso) => $curso->whereKey($id)))
            ->when(($filtros['contratos'] ?? '') === 'con', fn ($q) => $q->has('contratos'))
            ->when(($filtros['contratos'] ?? '') === 'sin', fn ($q) => $q->doesntHave('contratos'))
            ->when(($filtros['ministerios'] ?? '') === 'con', fn ($q) => $q->whereHas('ministerios', $ministeriosVigentes))
            ->when(($filtros['ministerios'] ?? '') === 'sin', fn ($q) => $q->whereDoesntHave('ministerios', $ministeriosVigentes))
            ->when($filtros['ministerio_id'] ?? null, fn ($q, $id) => $q->whereHas('ministerios', fn ($m) => $ministeriosVigentes($m)->where('ministerios.id', $id)));

        if ($estado !== '') {
            $query->where('estado', $estado);
        }
        if ($desde) {
            $query->whereDate('fecha_ingreso', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('fecha_ingreso', '<=', $hasta);
        }

        if ($request->filled('q')) {
            $query->with([
                'docente.sesiones' => fn ($sesiones) => $sesiones
                    ->whereHas('curso')
                    ->whereDoesntHave('asistencias')
                    ->with('curso')
                    ->orderBy('fecha')
                    ->orderBy('hora'),
            ]);
        }

        $miembros = $query->orderBy('nombre')->get();

        $totales = [
            'total' => $miembros->count(),
            'activos' => $miembros->filter(fn ($m) => $m->estado === EstadoMiembro::Activo)->count(),
            'inactivos' => $miembros->filter(fn ($m) => $m->estado === EstadoMiembro::Inactivo)->count(),
        ];

        $cursos = Curso::orderBy('nombre')->get();
        $ministerios = Ministerio::orderBy('nombre')->get();

        return view('reportes.miembros', compact('miembros', 'estado', 'desde', 'hasta', 'totales', 'filtros', 'cursos', 'ministerios'));
    }

    public function asistencia(Request $request)
    {
        $cursoId = $request->input('curso_id', '');
        $desde = $request->input('desde', '');
        $hasta = $request->input('hasta', '');

        $cursosQuery = Curso::query()->orderBy('nombre');
        $query = Asistencia::with(['miembro', 'sesion.curso']);

        if (auth()->user()->esDocente()) {
            $docente = auth()->user()->docente;

            if ($docente && $docente->activo) {
                $cursosQuery->paraDocente($docente->id);
                $query->whereHas('sesion', fn ($q) => $q->paraDocente($docente->id));
            } else {
                $cursosQuery->whereRaw('1 = 0');
                $query->whereRaw('1 = 0');
            }
        }

        $cursos = $cursosQuery->get();

        if ($cursoId) {
            $query->whereHas('sesion', fn ($q) => $q->where('id_curso', $cursoId));
        }
        if ($desde) {
            $query->whereHas('sesion', fn ($q) => $q->whereDate('fecha', '>=', $desde));
        }
        if ($hasta) {
            $query->whereHas('sesion', fn ($q) => $q->whereDate('fecha', '<=', $hasta));
        }

        $registros = $query->orderByDesc('sesion_id')->get();

        $resumen = [
            'presentes' => $registros->filter(fn ($a) => $a->estado === EstadoAsistencia::Presente)->count(),
            'ausentes' => $registros->filter(fn ($a) => $a->estado === EstadoAsistencia::Ausente)->count(),
            'tardanzas' => $registros->filter(fn ($a) => $a->estado === EstadoAsistencia::Tardanza)->count(),
            'justificados' => $registros->filter(fn ($a) => $a->estado === EstadoAsistencia::Justificado)->count(),
        ];

        return view('reportes.asistencia', compact('registros', 'cursos', 'cursoId', 'desde', 'hasta', 'resumen'));
    }

    public function financiero(Request $request)
    {
        $request->merge([
            'desde' => $request->filled('desde') ? $request->input('desde') : now()->startOfMonth()->toDateString(),
            'hasta' => $request->filled('hasta') ? $request->input('hasta') : now()->endOfMonth()->toDateString(),
        ]);
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'movimiento' => ['nullable', Rule::in(['ingresos', 'egresos'])],
            'tipo_ingreso' => ['nullable', Rule::enum(TipoIngreso::class)],
            'tipo_egreso' => ['nullable', Rule::enum(TipoEgreso::class)],
            'metodo_pago' => ['nullable', Rule::enum(MetodoPago::class)],
        ]);
        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? now()->endOfMonth()->toDateString();

        $ingresos = Ingreso::with([
            'pago.inscripcion' => fn ($query) => $query->withTrashed(),
            'pago.inscripcion.miembro' => fn ($query) => $query->withTrashed(),
        ])
            ->conPago()
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$desde, $hasta])
            ->when(($filtros['movimiento'] ?? '') === 'egresos', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($filtros['tipo_ingreso'] ?? null, fn ($q, $tipo) => $q->where('ingresos.tipo', $tipo))
            ->when($filtros['metodo_pago'] ?? null, fn ($q, $metodo) => $q->where(DB::raw(Ingreso::SQL_METODO), $metodo))
            ->orderByDesc(DB::raw(Ingreso::SQL_FECHA))
            ->get();
        $egresos = Egreso::whereBetween('fecha', [$desde, $hasta])
            ->when(($filtros['movimiento'] ?? '') === 'ingresos', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($filtros['tipo_egreso'] ?? null, fn ($q, $tipo) => $q->where('tipo_egreso', $tipo))
            ->orderByDesc('fecha')->get();

        $totalIngresos = $ingresos->sum('monto_total');
        $totalEgresos = $egresos->sum('monto');
        $balance = $totalIngresos - $totalEgresos;

        $ingresosPorTipo = $ingresos
            ->groupBy(fn ($i) => $i->tipo?->value ?? 'Sin clasificar')
            ->map(fn ($g) => $g->sum('monto_total'));
        $egresosPorTipo = $egresos
            ->groupBy(fn ($e) => $e->tipo_egreso?->value ?? 'Sin clasificar')
            ->map(fn ($g) => $g->sum('monto'));

        return view('reportes.financiero', compact(
            'filtros',
            'ingresos',
            'egresos',
            'desde',
            'hasta',
            'totalIngresos',
            'totalEgresos',
            'balance',
            'ingresosPorTipo',
            'egresosPorTipo'
        ));
    }
}
