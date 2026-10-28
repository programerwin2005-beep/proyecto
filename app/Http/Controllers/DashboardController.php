<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Asistencia;
use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Culto;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\Inscripcion;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Models\Sesion;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // El docente solo necesita sus sesiones y la asistencia; no debe ver
        // indicadores financieros globales del panel administrativo.
        if (auth()->user()->esDocente()) {
            return redirect()->route('sesiones.index');
        }

        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $finMes = now()->endOfMonth()->toDateString();

        $stats = [
            'miembros' => Miembro::count(),
            'miembros_activos' => Miembro::where('estado', 'Activo')->count(),
            'ministerios' => Ministerio::count(),
            'lideres' => LiderIglesia::where('activo', true)->count(),
            'cargos' => Cargo::count(),
            'cultos' => Culto::count(),
            'cursos' => Curso::count(),
            'docentes' => Docente::where('activo', true)->count(),
            'inscripciones' => Inscripcion::count(),
            'contratos_activos' => Contrato::where('activo', true)->count(),
            'ingresos_mes' => Ingreso::total($inicioMes, $finMes),
            'egresos_mes' => Egreso::whereBetween('fecha', [$inicioMes, $finMes])->sum('monto'),
        ];

        $stats['ingresos_totales'] = Ingreso::total();
        $stats['egresos_totales'] = Egreso::sum('monto');
        $stats['balance'] = $stats['ingresos_totales'] - $stats['egresos_totales'];
        $stats['balance_mes'] = $stats['ingresos_mes'] - $stats['egresos_mes'];

        $proximasActividades = Actividad::where('fecha', '>=', $hoy)
            ->with('ministerio')
            ->orderBy('fecha')
            ->take(5)
            ->get();

        $proximasSesiones = Sesion::where('fecha', '>=', $hoy)
            ->with(['curso', 'docente.miembro'])
            ->orderBy('fecha')
            ->take(5)
            ->get();

        $asistenciaStats = [
            'presentes' => Asistencia::where('estado', 'Presente')->count(),
            'ausentes' => Asistencia::where('estado', 'Ausente')->count(),
            'tardanzas' => Asistencia::where('estado', 'Tardanza')->count(),
        ];

        $expresionMes = DB::getDriverName() === 'sqlite'
            ? "strftime('%%Y-%%m', %s)"
            : "DATE_FORMAT(%s, '%%Y-%%m')";

        $ingresosUltimos6 = DB::table('ingresos')
            ->leftJoin('pago_cursos', 'pago_cursos.id', '=', 'ingresos.pago_curso_id')
            ->whereNull('ingresos.deleted_at')
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [
                now()->subMonths(5)->startOfMonth()->toDateString(),
                $finMes,
            ])
            ->select(
                DB::raw(sprintf($expresionMes, Ingreso::SQL_FECHA).' as mes'),
                DB::raw('SUM('.Ingreso::SQL_MONTO.') as total')
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $egresosUltimos6 = DB::table('egresos')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [now()->subMonths(5)->startOfMonth()->toDateString(), $finMes])
            ->select(
                DB::raw(sprintf($expresionMes, 'fecha').' as mes'),
                DB::raw('SUM(monto) as total')
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $ingresosPorTipo = DB::table('ingresos')
            ->leftJoin('pago_cursos', 'pago_cursos.id', '=', 'ingresos.pago_curso_id')
            ->whereNull('ingresos.deleted_at')
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$inicioMes, $finMes])
            ->select('tipo', DB::raw('SUM('.Ingreso::SQL_MONTO.') as total'))
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->pluck('total', 'tipo');

        $egresosPorTipo = Egreso::select('tipo_egreso', DB::raw('SUM(monto) as total'))
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('tipo_egreso')
            ->orderByDesc('total')
            ->pluck('total', 'tipo_egreso');

        return view('dashboard', compact(
            'stats',
            'proximasActividades',
            'proximasSesiones',
            'asistenciaStats',
            'ingresosUltimos6',
            'egresosUltimos6',
            'ingresosPorTipo',
            'egresosPorTipo'
        ));
    }
}
