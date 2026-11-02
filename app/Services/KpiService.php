<?php

namespace App\Services;

use App\Enums\EstadoActividad;
use App\Enums\EstadoAsistencia;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMiembro;
use App\Enums\TipoEgreso;
use App\Enums\TipoIngreso;
use App\Models\CategoriaFinanciera;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de los KPIs prioritarios del panel.
 *
 * Reglas comunes:
 * - El universo de ingresos es siempre `ingresos`; `pago_cursos` solo resuelve
 *   monto/fecha cuando la migración 2026_10_21 dejó esos campos en NULL.
 * - Cada tabla unida filtra su propio `deleted_at`.
 * - Los porcentajes devuelven null cuando el denominador es cero.
 * - Los rangos de fecha los decide el controlador, nunca este servicio.
 */
final class KpiService
{
    /**
     * KPIs visibles según el rol. El docente no llega aquí: el dashboard lo
     * redirige a sus sesiones.
     *
     * @return array<string, array<string, mixed>>
     */
    public function dashboard(User $user, string $desde, string $hasta, string $hoy): array
    {
        $esAdministrador = $user->esAdministrador();
        $esSecretario = $user->hasRole('Secretario');
        $esTesorero = $user->hasRole('Tesorero');

        $datos = [];

        if ($esAdministrador || $esSecretario) {
            $datos['membresia'] = $this->membresia($desde, $hasta);
            $datos['ministerios'] = $this->ministerios($desde, $hasta, $hoy);
            $datos['academia'] = $this->academia($desde, $hasta);
        }

        if ($esAdministrador || $esTesorero) {
            $datos['finanzas'] = $this->finanzas($desde, $hasta);
            $datos['cobranza'] = $this->cobranza();
            $datos['personal'] = $this->personal($hoy);
        }

        if ($esAdministrador) {
            $datos['seguridad'] = $this->seguridad($desde, $hasta);
            $datos['calidad'] = $this->calidad();
        }

        return $datos;
    }

    /**
     * A1 y A2.
     *
     * @return array<string, mixed>
     */
    public function membresia(string $desde, string $hasta): array
    {
        $fila = DB::table('miembros')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as activos', [EstadoMiembro::Activo->value])
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as inactivos', [EstadoMiembro::Inactivo->value])
            ->first();

        $total = (int) ($fila->total ?? 0);
        $activos = (int) ($fila->activos ?? 0);
        $inactivos = (int) ($fila->inactivos ?? 0);

        $altasMes = DB::table('miembros')
            ->whereNull('deleted_at')
            ->whereBetween('fecha_ingreso', [$desde, $hasta])
            ->count();

        $inicio6 = Carbon::parse($hasta)->subMonthsNoOverflow(5)->startOfMonth()->toDateString();

        $altasPorMes = DB::table('miembros')
            ->whereNull('deleted_at')
            ->whereBetween('fecha_ingreso', [$inicio6, $hasta])
            ->selectRaw($this->expresionMes('fecha_ingreso').' as mes')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $porMes = [];
        foreach ($this->mesesEntre($inicio6, $hasta) as $mes) {
            $porMes[] = [
                'mes' => $mes,
                'label' => $this->etiquetaMes($mes),
                'total' => (int) ($altasPorMes[$mes] ?? 0),
            ];
        }

        $mesesPrevios = collect(range(1, 3))
            ->map(fn (int $i) => Carbon::parse($desde)->subMonthsNoOverflow($i)->format('Y-m'));

        $promedio = $mesesPrevios->avg(fn (string $mes) => (float) ($altasPorMes[$mes] ?? 0));

        return [
            'total' => $total,
            'activos' => $activos,
            'inactivos' => $inactivos,
            'pct_activos' => $total > 0 ? round(100 * $activos / $total, 1) : null,
            'altas_mes' => $altasMes,
            'altas_promedio_3m' => round((float) $promedio, 1),
            'por_mes' => $porMes,
        ];
    }

    /**
     * B1, B2, B3, B4 y B5.
     *
     * @return array<string, mixed>
     */
    public function ministerios(string $desde, string $hasta, string $hoy): array
    {
        $total = DB::table('ministerios')->whereNull('deleted_at')->count();

        $sinLiderQuery = DB::table('ministerios')
            ->whereNull('deleted_at')
            ->whereNull('id_lider_iglesia');

        $sinLiderLista = (clone $sinLiderQuery)
            ->orderBy('nombre')
            ->limit(5)
            ->pluck('nombre')
            ->all();

        $porMinisterio = DB::table('ministerios as m')
            ->leftJoin('miembro_ministerio as mm', function ($join) use ($hoy) {
                $join->on('mm.ministerio_id', '=', 'm.id')
                    ->whereNull('mm.deleted_at')
                    ->where(function ($query) use ($hoy) {
                        $query->whereNull('mm.fecha_fin')
                            ->orWhere('mm.fecha_fin', '>=', $hoy);
                    });
            })
            ->leftJoin('miembros as mi', function ($join) {
                $join->on('mi.id', '=', 'mm.miembro_id')
                    ->whereNull('mi.deleted_at');
            })
            ->whereNull('m.deleted_at')
            ->groupBy('m.id', 'm.nombre')
            ->select('m.id', 'm.nombre')
            ->selectRaw('COUNT(DISTINCT mi.id) as miembros')
            ->orderBy('m.nombre')
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'miembros' => (int) $fila->miembros,
            ])
            ->all();

        $baseActividades = DB::table('actividades')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$desde, $hasta]);

        // Se agrupa por la expresión completa y no por el alias: MySQL
        // resuelve `estado` contra la columna real de la tabla y separaría
        // los valores heredados que la expresión unifica.
        $expresionEstado = $this->sqlEstadoActividad('estado');

        $estados = (clone $baseActividades)
            ->selectRaw($expresionEstado.' as estado')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($expresionEstado)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'estado' => $fila->estado,
                'total' => (int) $fila->total,
            ])
            ->all();

        $ultimosSeguimientos = DB::table('seguimiento_actividades')
            ->select('id_actividad')
            ->selectRaw('MAX(id) as ultimo_id')
            ->whereNull('deleted_at')
            ->groupBy('id_actividad');

        $avancePromedio = DB::table('actividades as a')
            ->joinSub($ultimosSeguimientos, 'u', fn ($join) => $join->on('u.id_actividad', '=', 'a.id'))
            ->join('seguimiento_actividades as s', function ($join) {
                $join->on('s.id', '=', 'u.ultimo_id')
                    ->whereNull('s.deleted_at');
            })
            ->whereNull('a.deleted_at')
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->avg('s.porcentaje_avance');

        $atrasadasQuery = DB::table('actividades as a')
            ->leftJoinSub($ultimosSeguimientos, 'u', fn ($join) => $join->on('u.id_actividad', '=', 'a.id'))
            ->leftJoin('seguimiento_actividades as s', function ($join) {
                $join->on('s.id', '=', 'u.ultimo_id')
                    ->whereNull('s.deleted_at');
            })
            ->whereNull('a.deleted_at')
            ->where(function ($query) use ($hoy) {
                $query->where(function ($vencidas) use ($hoy) {
                    $vencidas->where('a.fecha', '<', $hoy)
                        ->whereNotIn('a.estado', [
                            EstadoActividad::Completada->value,
                            EstadoActividad::Cancelada->value,
                            'Completado',
                            'Cancelado',
                            'Finalizado',
                        ]);
                })->orWhere(function ($sinAvance) {
                    $sinAvance->whereNotNull('s.porcentaje_avance')
                        ->where('s.porcentaje_avance', '<', 50);
                });
            });

        $atrasadasTotal = (clone $atrasadasQuery)->count();

        $atrasadas = (clone $atrasadasQuery)
            ->select('a.id', 'a.nombre', 'a.fecha')
            ->selectRaw($this->sqlEstadoActividad('a.estado').' as estado')
            ->selectRaw('s.porcentaje_avance as avance')
            ->orderBy('a.fecha')
            ->limit(8)
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'fecha' => $fila->fecha,
                'estado' => $fila->estado,
                'avance' => $fila->avance !== null ? (float) $fila->avance : null,
            ])
            ->all();

        return [
            'total' => $total,
            'sin_lider' => (clone $sinLiderQuery)->count(),
            'sin_lider_lista' => $sinLiderLista,
            'por_ministerio' => $porMinisterio,
            'actividades_total' => (clone $baseActividades)->count(),
            'estados' => $estados,
            'avance_promedio' => $avancePromedio !== null ? round((float) $avancePromedio, 1) : null,
            'atrasadas' => $atrasadasTotal,
            'atrasadas_lista' => $atrasadas,
        ];
    }

    /**
     * C1, C2 y C3 (y el conteo auxiliar C4).
     *
     * @return array<string, mixed>
     */
    public function academia(string $desde, string $hasta): array
    {
        $cursos = DB::table('cursos as c')
            ->leftJoin('inscripciones as i', function ($join) {
                $join->on('i.curso_id', '=', 'c.id')
                    ->whereNull('i.deleted_at')
                    ->where('i.estado', EstadoInscripcion::Activo->value);
            })
            ->whereNull('c.deleted_at')
            ->where('c.f_inicio', '<=', $hasta)
            ->where(function ($query) use ($desde) {
                $query->whereNull('c.f_fin')
                    ->orWhere('c.f_fin', '>=', $desde);
            })
            ->groupBy('c.id', 'c.nombre', 'c.cupo_max')
            ->select('c.id', 'c.nombre', 'c.cupo_max')
            ->selectRaw('COUNT(i.id) as inscritos')
            ->orderBy('c.nombre')
            ->get();

        $ultimasAsistencias = DB::table('asistencias_sesion')
            ->select('sesion_id', 'miembro_id')
            ->selectRaw('MAX(id) as ultimo_id')
            ->whereNull('deleted_at')
            ->groupBy('sesion_id', 'miembro_id');

        $asistenciaPorCurso = DB::table('asistencias_sesion as a')
            ->joinSub($ultimasAsistencias, 'u', fn ($join) => $join->on('u.ultimo_id', '=', 'a.id'))
            ->join('sesiones as s', function ($join) use ($desde, $hasta) {
                $join->on('s.id', '=', 'a.sesion_id')
                    ->whereNull('s.deleted_at')
                    ->whereBetween('s.fecha', [$desde, $hasta]);
            })
            ->join('cursos as c', function ($join) {
                $join->on('c.id', '=', 's.id_curso')
                    ->whereNull('c.deleted_at');
            })
            ->whereNull('a.deleted_at')
            ->groupBy('c.id', 'c.nombre')
            ->select('c.id', 'c.nombre')
            ->selectRaw('COUNT(*) as registros')
            ->selectRaw('SUM(CASE WHEN a.estado = ? THEN 1 ELSE 0 END) as presentes', [EstadoAsistencia::Presente->value])
            ->selectRaw('SUM(CASE WHEN a.estado = ? THEN 1 ELSE 0 END) as tardanzas', [EstadoAsistencia::Tardanza->value])
            ->selectRaw('SUM(CASE WHEN a.estado = ? THEN 1 ELSE 0 END) as ausentes', [EstadoAsistencia::Ausente->value])
            ->selectRaw('SUM(CASE WHEN a.estado = ? THEN 1 ELSE 0 END) as justificados', [EstadoAsistencia::Justificado->value])
            ->get()
            ->keyBy('id');

        $cupoTotal = 0;
        $inscritosTotal = 0;
        $bajaOcupacion = 0;
        $cursosData = [];

        foreach ($cursos as $curso) {
            $cupo = (int) $curso->cupo_max;
            $inscritos = (int) $curso->inscritos;
            $ocupacion = $cupo > 0 ? round(100 * $inscritos / $cupo, 1) : null;
            $asistencia = $asistenciaPorCurso->get($curso->id);

            $registros = (int) ($asistencia->registros ?? 0);
            $presentes = (int) ($asistencia->presentes ?? 0);
            $tardanzas = (int) ($asistencia->tardanzas ?? 0);
            $ausentes = (int) ($asistencia->ausentes ?? 0);

            if ($ocupacion !== null && $ocupacion < 50) {
                $bajaOcupacion++;
            }

            $cupoTotal += $cupo;
            $inscritosTotal += $inscritos;

            $cursosData[] = [
                'id' => (int) $curso->id,
                'nombre' => $curso->nombre,
                'cupo_max' => $cupo,
                'inscritos' => $inscritos,
                'ocupacion_pct' => $ocupacion,
                'registros' => $registros,
                'asistencia_pct' => $registros > 0 ? round(100 * ($presentes + $tardanzas) / $registros, 1) : null,
                'ausentismo_pct' => $registros > 0 ? round(100 * $ausentes / $registros, 1) : null,
            ];
        }

        $registros = (int) $asistenciaPorCurso->sum('registros');
        $presentes = (int) $asistenciaPorCurso->sum('presentes');
        $tardanzas = (int) $asistenciaPorCurso->sum('tardanzas');
        $ausentes = (int) $asistenciaPorCurso->sum('ausentes');
        $justificados = (int) $asistenciaPorCurso->sum('justificados');

        return [
            'cupo_total' => $cupoTotal,
            'inscritos' => $inscritosTotal,
            'ocupacion_pct' => $cupoTotal > 0 ? round(100 * $inscritosTotal / $cupoTotal, 1) : null,
            'registros' => $registros,
            'presentes' => $presentes,
            'tardanzas' => $tardanzas,
            'ausentes' => $ausentes,
            'justificados' => $justificados,
            'asistencia_pct' => $registros > 0 ? round(100 * ($presentes + $tardanzas) / $registros, 1) : null,
            'ausentismo_pct' => $registros > 0 ? round(100 * $ausentes / $registros, 1) : null,
            'baja_ocupacion' => $bajaOcupacion,
            'cursos' => $cursosData,
        ];
    }

    /**
     * D1, D2, D3 y D4.
     *
     * @return array<string, mixed>
     */
    public function finanzas(string $desde, string $hasta): array
    {
        $ingresos = $this->ingresosEntre($desde, $hasta);
        $egresos = (float) DB::table('egresos')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $desdePrevio = Carbon::parse($desde)->subMonthNoOverflow()->startOfMonth()->toDateString();
        $hastaPrevio = Carbon::parse($desde)->subMonthNoOverflow()->endOfMonth()->toDateString();

        $ingresosPrevios = $this->ingresosEntre($desdePrevio, $hastaPrevio);
        $egresosPrevios = (float) DB::table('egresos')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$desdePrevio, $hastaPrevio])
            ->sum('monto');

        $inicio6 = Carbon::parse($hasta)->subMonthsNoOverflow(5)->startOfMonth()->toDateString();

        $ingresosMes = $this->ingresosQuery()
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$inicio6, $hasta])
            ->selectRaw($this->expresionMes(Ingreso::SQL_FECHA).' as mes')
            ->selectRaw('SUM('.Ingreso::SQL_MONTO.') as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $egresosMes = DB::table('egresos')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$inicio6, $hasta])
            ->selectRaw($this->expresionMes('fecha').' as mes')
            ->selectRaw('SUM(monto) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $porMes = [];
        foreach ($this->mesesEntre($inicio6, $hasta) as $mes) {
            $in = (float) ($ingresosMes[$mes] ?? 0);
            $eg = (float) ($egresosMes[$mes] ?? 0);
            $porMes[] = [
                'mes' => $mes,
                'label' => $this->etiquetaMes($mes),
                'ingresos' => round($in, 2),
                'egresos' => round($eg, 2),
                'saldo' => round($in - $eg, 2),
            ];
        }

        // `tipo` también es una columna real de `ingresos`, por lo que MySQL
        // interpreta `GROUP BY tipo` como `ingresos.tipo` y rechaza la
        // consulta con ONLY_FULL_GROUP_BY. Se agrupa por la expresión.
        $expresionTipo = "COALESCE(cat.nombre, {$this->sqlTipoIngreso('ingresos.tipo')})";

        $composicion = $this->ingresosQuery()
            ->leftJoin('categorias_financieras as cat', function ($join) {
                $join->on('cat.id', '=', 'ingresos.categoria_id')
                    ->whereNull('cat.deleted_at');
            })
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$desde, $hasta])
            ->selectRaw("{$expresionTipo} as tipo")
            ->selectRaw('SUM('.Ingreso::SQL_MONTO.') as total')
            ->groupByRaw($expresionTipo)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'tipo' => $fila->tipo,
                'total' => round((float) $fila->total, 2),
                'pct' => $ingresos > 0 ? round(100 * (float) $fila->total / $ingresos, 1) : null,
            ])
            ->all();

        $expresionCulto = "COALESCE(cultos.nombre, 'Sin culto')";

        $porCulto = $this->ingresosQuery()
            ->leftJoin('cultos', function ($join) {
                $join->on('cultos.id', '=', 'ingresos.id_culto')
                    ->whereNull('cultos.deleted_at');
            })
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$desde, $hasta])
            ->where(function ($query) {
                $query->where('ingresos.tipo', 'LIKE', 'Diezmo%')
                    ->orWhere('ingresos.tipo', 'LIKE', 'Ofrenda%');
            })
            ->selectRaw("{$expresionCulto} as culto")
            ->selectRaw("SUM(CASE WHEN ingresos.tipo LIKE 'Diezmo%' THEN ".Ingreso::SQL_MONTO.' ELSE 0 END) as diezmo')
            ->selectRaw("SUM(CASE WHEN ingresos.tipo LIKE 'Ofrenda%' THEN ".Ingreso::SQL_MONTO.' ELSE 0 END) as ofrenda')
            ->groupByRaw($expresionCulto)
            ->orderByDesc('diezmo')
            ->get()
            ->map(fn ($fila) => [
                'culto' => $fila->culto,
                'diezmo' => round((float) $fila->diezmo, 2),
                'ofrenda' => round((float) $fila->ofrenda, 2),
                'total' => round((float) $fila->diezmo + (float) $fila->ofrenda, 2),
            ])
            ->all();

        $diezmos = array_sum(array_column($porCulto, 'diezmo'));
        $ofrendas = array_sum(array_column($porCulto, 'ofrenda'));

        $categoriaSalarios = CategoriaFinanciera::idPara(
            TipoEgreso::Salarios->value,
            CategoriaFinanciera::NATURALEZA_EGRESO
        );

        // Gasto de personal: contrato, pago directo a líder o categoría de
        // salarios. Cada fila de `egresos` se cuenta una sola vez aunque tenga
        // contrato y líder a la vez (la validación lo impide, pero la consulta
        // es igualmente segura).
        $personal = (float) DB::table('egresos')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($query) use ($categoriaSalarios) {
                $query->whereNotNull('id_contrato')
                    ->orWhereNotNull('id_lider_iglesia')
                    ->orWhereIn('tipo_egreso', [
                        TipoEgreso::Salarios->value,
                        'Salario',
                        'Sueldo',
                        'Salarios / Honorarios',
                    ]);

                if ($categoriaSalarios !== null) {
                    $query->orWhere('categoria_id', $categoriaSalarios);
                }
            })
            ->sum('monto');

        return [
            'ingresos' => round($ingresos, 2),
            'egresos' => round($egresos, 2),
            'saldo' => round($ingresos - $egresos, 2),
            'ingresos_previos' => round($ingresosPrevios, 2),
            'egresos_previos' => round($egresosPrevios, 2),
            'saldo_previo' => round($ingresosPrevios - $egresosPrevios, 2),
            'variacion_ingresos_pct' => $ingresosPrevios > 0
                ? round(100 * ($ingresos - $ingresosPrevios) / $ingresosPrevios, 1)
                : null,
            'variacion_egresos_pct' => $egresosPrevios > 0
                ? round(100 * ($egresos - $egresosPrevios) / $egresosPrevios, 1)
                : null,
            'por_mes' => $porMes,
            'composicion' => $composicion,
            'diezmos' => round($diezmos, 2),
            'ofrendas' => round($ofrendas, 2),
            'por_culto' => $porCulto,
            'personal' => round($personal, 2),
            'pct_personal' => $ingresos > 0 ? round(100 * $personal / $ingresos, 1) : null,
        ];
    }

    /**
     * E1, E2 y E3. Se calcula por inscripción para poder obtener las tres
     * vistas sin repetir la lógica de saldo.
     *
     * @return array<string, mixed>
     */
    public function cobranza(): array
    {
        $pagos = DB::table('pago_cursos')
            ->select('inscripcion_id')
            ->selectRaw('SUM(monto) as pagado')
            ->whereNull('deleted_at')
            ->groupBy('inscripcion_id');

        $filas = DB::table('inscripciones as i')
            ->join('cursos as c', function ($join) {
                $join->on('c.id', '=', 'i.curso_id')
                    ->whereNull('c.deleted_at')
                    ->where('c.tiene_pago', 1);
            })
            ->leftJoinSub($pagos, 'p', fn ($join) => $join->on('p.inscripcion_id', '=', 'i.id'))
            ->whereNull('i.deleted_at')
            ->where('i.estado', EstadoInscripcion::Activo->value)
            ->select('i.id', 'i.curso_id', 'i.fecha_inscripcion', 'c.nombre as curso', 'c.monto_inscripcion')
            ->selectRaw('COALESCE(p.pagado, 0) as pagado')
            ->get();

        $cursos = [];
        $estados = [
            'Pagado' => ['alumnos' => 0, 'cobrado' => 0.0, 'saldo' => 0.0],
            'Pago Parcial' => ['alumnos' => 0, 'cobrado' => 0.0, 'saldo' => 0.0],
            'Pendiente' => ['alumnos' => 0, 'cobrado' => 0.0, 'saldo' => 0.0],
        ];

        $esperadoTotal = 0.0;
        $cobradoTotal = 0.0;
        $saldoTotal = 0.0;
        $conSaldo = 0;
        $alumnosTotal = 0;

        foreach ($filas as $fila) {
            $esperado = (float) $fila->monto_inscripcion;
            $pagado = (float) $fila->pagado;
            $saldo = max(0.0, $esperado - $pagado);
            $estado = $saldo <= 0 ? 'Pagado' : ($pagado > 0 ? 'Pago Parcial' : 'Pendiente');

            $esperadoTotal += $esperado;
            $cobradoTotal += $pagado;
            $saldoTotal += $saldo;
            $alumnosTotal++;

            if ($saldo > 0) {
                $conSaldo++;
            }

            $estados[$estado]['alumnos']++;
            $estados[$estado]['cobrado'] += $pagado;
            $estados[$estado]['saldo'] += $saldo;

            $clave = (int) $fila->curso_id;
            if (! isset($cursos[$clave])) {
                $cursos[$clave] = [
                    'id' => $clave,
                    'nombre' => $fila->curso,
                    'inscritos' => 0,
                    'esperado' => 0.0,
                    'cobrado' => 0.0,
                    'saldo' => 0.0,
                    'pct' => null,
                ];
            }

            $cursos[$clave]['inscritos']++;
            $cursos[$clave]['esperado'] += $esperado;
            $cursos[$clave]['cobrado'] += $pagado;
            $cursos[$clave]['saldo'] += $saldo;
        }

        foreach ($cursos as &$curso) {
            $curso['esperado'] = round($curso['esperado'], 2);
            $curso['cobrado'] = round($curso['cobrado'], 2);
            $curso['saldo'] = round($curso['saldo'], 2);
            $curso['pct'] = $curso['esperado'] > 0
                ? round(100 * $curso['cobrado'] / $curso['esperado'], 1)
                : null;
        }
        unset($curso);

        foreach ($estados as &$estado) {
            $estado['cobrado'] = round($estado['cobrado'], 2);
            $estado['saldo'] = round($estado['saldo'], 2);
        }
        unset($estado);

        return [
            'esperado' => round($esperadoTotal, 2),
            'cobrado' => round($cobradoTotal, 2),
            'saldo' => round($saldoTotal, 2),
            'pct' => $esperadoTotal > 0 ? round(100 * $cobradoTotal / $esperadoTotal, 1) : null,
            'inscripciones_con_saldo' => $conSaldo,
            'saldo_promedio' => $conSaldo > 0 ? round($saldoTotal / $conSaldo, 2) : 0.0,
            'alumnos' => $alumnosTotal,
            'estados' => $estados,
            'cursos' => array_values($cursos),
        ];
    }

    /**
     * F1 y F2.
     *
     * @return array<string, mixed>
     */
    public function personal(string $hoy): array
    {
        $contratos = DB::table('contratos as c')
            ->leftJoin('miembros as m', function ($join) {
                $join->on('m.id', '=', 'c.id_miembro')
                    ->whereNull('m.deleted_at');
            })
            ->whereNull('c.deleted_at')
            ->select('c.id', 'c.fecha_inicio', 'c.fecha_fin', 'c.activo', 'm.nombre as miembro')
            ->get();

        $hoyCarbon = Carbon::parse($hoy)->startOfDay();
        $limite = $hoyCarbon->copy()->addDays(30);

        $conteo = [
            'vigentes' => 0,
            'por_vencer' => 0,
            'vencidos' => 0,
            'futuros' => 0,
        ];

        $porVencer = [];

        foreach ($contratos as $contrato) {
            $inicio = Carbon::parse($contrato->fecha_inicio)->startOfDay();
            $fin = $contrato->fecha_fin ? Carbon::parse($contrato->fecha_fin)->startOfDay() : null;

            if ($inicio->gt($hoyCarbon)) {
                $conteo['futuros']++;
            } elseif ($fin !== null && $fin->lt($hoyCarbon)) {
                $conteo['vencidos']++;
            } elseif ($fin !== null && $fin->lte($limite)) {
                $conteo['por_vencer']++;
                $porVencer[] = [
                    'id' => (int) $contrato->id,
                    'miembro' => $contrato->miembro ?? 'Sin miembro',
                    'fecha_fin' => $fin->toDateString(),
                    'dias' => (int) $hoyCarbon->diffInDays($fin),
                ];
            } else {
                $conteo['vigentes']++;
            }
        }

        usort($porVencer, fn (array $a, array $b) => $a['fecha_fin'] <=> $b['fecha_fin']);

        return [
            'total' => count($contratos),
            'vigentes' => $conteo['vigentes'],
            'por_vencer' => $conteo['por_vencer'],
            'vencidos' => $conteo['vencidos'],
            'futuros' => $conteo['futuros'],
            'por_vencer_lista' => $porVencer,
        ];
    }

    /**
     * G1, G2 y G4.
     *
     * @return array<string, mixed>
     */
    public function seguridad(?string $desde = null, ?string $hasta = null): array
    {
        $roles = DB::table('roles as r')
            ->leftJoin('role_user as ru', function ($join) {
                $join->on('ru.role_id', '=', 'r.id')
                    ->whereNull('ru.deleted_at');
            })
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 'ru.user_id')
                    ->whereNull('u.deleted_at');
            })
            ->whereNull('r.deleted_at')
            ->groupBy('r.id', 'r.nombre')
            ->select('r.id', 'r.nombre')
            ->selectRaw('COUNT(DISTINCT u.id) as usuarios')
            ->orderBy('r.nombre')
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'usuarios' => (int) $fila->usuarios,
            ])
            ->all();

        $sinRolQuery = DB::table('users as u')
            ->whereNull('u.deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('role_user as ru')
                    ->join('roles as r', function ($join) {
                        $join->on('r.id', '=', 'ru.role_id')
                            ->whereNull('r.deleted_at');
                    })
                    ->whereNull('ru.deleted_at')
                    ->whereColumn('ru.user_id', 'u.id');
            });

        $sinRolLista = (clone $sinRolQuery)
            ->select('u.name', 'u.email')
            ->orderBy('u.name')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'name' => $fila->name,
                'email' => $fila->email,
            ])
            ->all();

        $limiteInactividad = Carbon::now()->subDays(30);

        $inactivosQuery = DB::table('users')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($limiteInactividad) {
                $query->whereNull('last_login_at')
                    ->orWhere('last_login_at', '<', $limiteInactividad);
            });

        $inactivosLista = (clone $inactivosQuery)
            ->select('name', 'email', 'last_login_at')
            ->orderByRaw('last_login_at IS NULL DESC')
            ->orderBy('last_login_at')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'name' => $fila->name,
                'email' => $fila->email,
                'last_login_at' => $fila->last_login_at,
            ])
            ->all();

        $modulosQuery = DB::table('audit_logs')
            ->select('modulo')
            ->selectRaw('COUNT(*) as total');

        if ($desde !== null && $hasta !== null) {
            $modulosQuery->whereBetween('created_at', [
                Carbon::parse($desde)->startOfDay(),
                Carbon::parse($hasta)->endOfDay(),
            ]);
        }

        $modulos = $modulosQuery
            ->groupBy('modulo')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'modulo' => $fila->modulo,
                'total' => (int) $fila->total,
            ])
            ->all();

        return [
            'total_usuarios' => DB::table('users')->whereNull('deleted_at')->count(),
            'roles' => $roles,
            'sin_rol' => (clone $sinRolQuery)->count(),
            'sin_rol_lista' => $sinRolLista,
            'inactivos_30' => (clone $inactivosQuery)->count(),
            'inactivos_lista' => $inactivosLista,
            'modulos' => $modulos,
        ];
    }

    /**
     * Resumen de la última importación de calidad (coverage, tests y SAST).
     *
     * @return array<string, mixed>
     */
    public function calidad(): array
    {
        $metricas = DB::table('metricas_calidad')
            ->orderByDesc('capturado_en')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $ultima = $metricas->first()?->capturado_en;

        $resumen = [
            'cobertura' => null,
            'tests' => null,
            'fallos' => null,
            'hallazgos' => (int) DB::table('hallazgos_seguridad')
                ->where('estado', 'abierto')
                ->count(),
            'capturado_en' => $ultima,
        ];

        foreach ($metricas as $metrica) {
            if ($ultima !== null && $metrica->capturado_en !== $ultima) {
                continue;
            }

            $resumen[$metrica->metrica] = $metrica->valor !== null
                ? (float) $metrica->valor
                : $metrica->texto;
        }

        return $resumen;
    }

    private function ingresosQuery(): Builder
    {
        return DB::table('ingresos')
            ->leftJoin('pago_cursos', function ($join) {
                $join->on('pago_cursos.id', '=', 'ingresos.pago_curso_id')
                    ->whereNull('pago_cursos.deleted_at');
            })
            ->whereNull('ingresos.deleted_at');
    }

    private function ingresosEntre(string $desde, string $hasta): float
    {
        return (float) $this->ingresosQuery()
            ->whereBetween(DB::raw(Ingreso::SQL_FECHA), [$desde, $hasta])
            ->sum(DB::raw(Ingreso::SQL_MONTO));
    }

    private function expresionMes(string $columna): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$columna})"
            : "DATE_FORMAT({$columna}, '%Y-%m')";
    }

    /**
     * @return list<string>
     */
    private function mesesEntre(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde)->startOfMonth();
        $fin = Carbon::parse($hasta)->startOfMonth();
        $meses = [];

        while ($inicio->lte($fin)) {
            $meses[] = $inicio->format('Y-m');
            $inicio->addMonthNoOverflow();
        }

        return $meses;
    }

    private function etiquetaMes(string $mes): string
    {
        return ucfirst(Carbon::createFromFormat('Y-m', $mes)->locale('es')->translatedFormat('M Y'));
    }

    private function sqlEstadoActividad(string $columna): string
    {
        $programado = EstadoActividad::Programado->value;
        $enProgreso = EstadoActividad::EnProgreso->value;
        $completada = EstadoActividad::Completada->value;
        $cancelada = EstadoActividad::Cancelada->value;

        return "CASE
            WHEN {$columna} IS NULL OR TRIM({$columna}) = '' THEN 'Sin estado'
            WHEN LOWER({$columna}) LIKE 'program%' THEN '{$programado}'
            WHEN LOWER({$columna}) LIKE 'en progreso%' OR LOWER({$columna}) LIKE 'en curso%' THEN '{$enProgreso}'
            WHEN LOWER({$columna}) LIKE 'complet%' OR LOWER({$columna}) LIKE 'finaliz%' THEN '{$completada}'
            WHEN LOWER({$columna}) LIKE 'cancel%' THEN '{$cancelada}'
            ELSE {$columna}
        END";
    }

    private function sqlTipoIngreso(string $columna): string
    {
        $diezmo = TipoIngreso::Diezmo->value;
        $ofrenda = TipoIngreso::Ofrenda->value;
        $donacion = TipoIngreso::Donacion->value;
        $inscripcion = TipoIngreso::Inscripcion->value;
        $actividad = TipoIngreso::Actividad->value;

        return "CASE
            WHEN {$columna} LIKE 'Diezmo%' THEN '{$diezmo}'
            WHEN {$columna} LIKE 'Ofrenda%' THEN '{$ofrenda}'
            WHEN {$columna} LIKE 'Donaci%' THEN '{$donacion}'
            WHEN {$columna} LIKE 'Inscripci%' THEN '{$inscripcion}'
            WHEN {$columna} LIKE '%Actividad%' THEN '{$actividad}'
            ELSE COALESCE(NULLIF(TRIM({$columna}), ''), 'Sin tipo')
        END";
    }
}
