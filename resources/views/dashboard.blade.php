@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    // Escala del gráfico: máximo entre ingresos, egresos y 1 (evita división por cero).
    $valoresGrafico = array_merge(
        array_values($ingresosUltimos6->toArray()),
        array_values($egresosUltimos6->toArray())
    );
    $hayMovimientos = count(array_filter($valoresGrafico, fn ($v) => (float) $v > 0)) > 0;
    $maxMes = max(array_merge($valoresGrafico, [1]));
    $marcas = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

    // Asistencia: totales acumulados de todos los registros (contexto real, no se mezclan sesiones).
    $asistTotal = $asistenciaStats['presentes'] + $asistenciaStats['ausentes'] + $asistenciaStats['tardanzas'];
    $pctAsist = fn ($n) => $asistTotal > 0 ? round(($n / $asistTotal) * 100) : 0;

    // Accesos rápidos visibles según el mismo rol que exigen las rutas (no altera permisos).
    $puedeParametrizacion = auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Secretario');
    $puedeFinanzas = auth()->user()->hasRole('Administrador') || auth()->user()->hasRole('Tesorero');

    $mesActual = ucfirst(now()->locale('es')->translatedFormat('F Y'));

    $kpis = $kpis ?? [];
    $kpiMembresia = $kpis['membresia'] ?? null;
    $kpiMinisterios = $kpis['ministerios'] ?? null;
    $kpiAcademia = $kpis['academia'] ?? null;
    $kpiFinanzas = $kpis['finanzas'] ?? null;
    $kpiCobranza = $kpis['cobranza'] ?? null;
    $kpiPersonal = $kpis['personal'] ?? null;
    $kpiSeguridad = $kpis['seguridad'] ?? null;

    // Nivel del semáforo: define el color del punto de cada indicador.
    $nivel = function ($valor, $limiteOk, $limiteAviso, $mayorEsMejor = true) {
        if ($valor === null) {
            return 'info';
        }
        if ($mayorEsMejor) {
            return $valor >= $limiteOk ? 'ok' : ($valor >= $limiteAviso ? 'late' : 'no');
        }
        return $valor <= $limiteOk ? 'ok' : ($valor <= $limiteAviso ? 'late' : 'no');
    };

    $alertaActividades = $kpiMinisterios['atrasadas_lista'] ?? [];
    $alertaContratos = $kpiPersonal['por_vencer_lista'] ?? [];
    $alertaCursos = collect($kpiCobranza['cursos'] ?? [])->where('saldo', '>', 0)->sortByDesc('saldo')->take(5);
    $alertaUsuarios = $kpiSeguridad['sin_rol_lista'] ?? [];
    $alertaInactivos = $kpiSeguridad['inactivos_lista'] ?? [];
    $hayAlertas = count($alertaActividades) > 0
        || count($alertaContratos) > 0
        || $alertaCursos->isNotEmpty()
        || count($alertaUsuarios) > 0
        || count($alertaInactivos) > 0;
@endphp

<div class="dash">
    {{-- ============================ 1. Encabezado ============================ --}}
    <header class="dash-header">
        <div class="dash-header__intro">
            <h1 class="dash-header__title">Dashboard</h1>
            <p class="dash-header__subtitle">Resumen general de la gestión de la iglesia</p>
        </div>
        <div class="dash-header__tools">
            <span class="dash-chip" title="Período financiero mostrado">{{ $mesActual }}</span>
            <button type="button" class="dash-refresh" onclick="window.location.reload()" title="Actualizar datos" aria-label="Actualizar datos">
                Actualizar
            </button>
        </div>
    </header>

    {{-- ==================== 2. Estadísticas principales ==================== --}}
    <section class="dash-kpis" aria-label="Estadísticas principales">
        <article class="dash-kpi">
            <span class="dash-kpi__icon" aria-hidden="true"></span>
            <div class="dash-kpi__body">
                <span class="dash-kpi__label">Miembros registrados</span>
                <span class="dash-kpi__value">{{ number_format($stats['miembros']) }}</span>
                <span class="dash-kpi__sub">{{ number_format($stats['miembros_activos']) }} activos</span>
            </div>
        </article>

        <article class="dash-kpi">
            <span class="dash-kpi__icon" aria-hidden="true"></span>
            <div class="dash-kpi__body">
                <span class="dash-kpi__label">Ministerios</span>
                <span class="dash-kpi__value">{{ number_format($stats['ministerios']) }}</span>
                <span class="dash-kpi__sub">{{ number_format($stats['lideres']) }} líderes activos</span>
            </div>
        </article>

        <article class="dash-kpi">
            <span class="dash-kpi__icon" aria-hidden="true"></span>
            <div class="dash-kpi__body">
                <span class="dash-kpi__label">Ingresos del mes</span>
                <span class="dash-kpi__value dash-kpi__value--money">Bs {{ number_format((float) $stats['ingresos_mes'], 2) }}</span>
                <span class="dash-kpi__sub">Total histórico: Bs {{ number_format((float) $stats['ingresos_totales'], 2) }}</span>
            </div>
        </article>

        <article class="dash-kpi">
            <span class="dash-kpi__icon" aria-hidden="true"></span>
            <div class="dash-kpi__body">
                <span class="dash-kpi__label">Egresos del mes</span>
                <span class="dash-kpi__value dash-kpi__value--money">Bs {{ number_format((float) $stats['egresos_mes'], 2) }}</span>
                <span class="dash-kpi__sub">Balance del mes: Bs {{ number_format((float) $stats['balance_mes'], 2) }}</span>
            </div>
        </article>
    </section>

    {{-- ============ 3. Flujo financiero + Resumen general ============ --}}
    <div class="dash-grid dash-grid--fin">
        <section class="dash-panel" aria-labelledby="dash-chart-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-chart-title">Flujo Financiero</h2>
                    <p class="dash-panel__desc">Ingresos y egresos de los últimos 6 meses</p>
                </div>
                <div class="dash-legend">
                    <span><i class="dash-legend__dot dash-legend__dot--in"></i>Ingresos</span>
                    <span><i class="dash-legend__dot dash-legend__dot--out"></i>Egresos</span>
                </div>
            </div>

            @if($hayMovimientos)
            <figure class="dash-chart" aria-label="Gráfico de ingresos y egresos de los últimos 6 meses">
                @foreach($marcas as $marca)
                    @php
                        $in = (float) ($ingresosUltimos6[$marca] ?? 0);
                        $eg = (float) ($egresosUltimos6[$marca] ?? 0);
                        $pIn = $in > 0 ? max((int) round(($in / $maxMes) * 100), 2) : 0;
                        $pEg = $eg > 0 ? max((int) round(($eg / $maxMes) * 100), 2) : 0;
                        $fechaMes = \Carbon\Carbon::createFromFormat('Y-m', $marca)->locale('es');
                        $mesCorto = ucfirst($fechaMes->translatedFormat('M'));
                        $mesLargo = ucfirst($fechaMes->translatedFormat('F Y'));
                    @endphp
                    <div class="dash-chart__col">
                        <div class="dash-chart__bars">
                            <span class="dash-bar dash-bar--in" style="height: {{ $pIn }}%" data-tip="Ingresos {{ $mesLargo }}: Bs {{ number_format($in, 2) }}" aria-label="Ingresos de {{ $mesLargo }}: {{ number_format($in, 2) }} bolivianos"></span>
                            <span class="dash-bar dash-bar--out" style="height: {{ $pEg }}%" data-tip="Egresos {{ $mesLargo }}: Bs {{ number_format($eg, 2) }}" aria-label="Egresos de {{ $mesLargo }}: {{ number_format($eg, 2) }} bolivianos"></span>
                        </div>
                        <span class="dash-chart__label">{{ $mesCorto }}</span>
                    </div>
                @endforeach
            </figure>
            @else
            <div class="dash-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                <p>Sin movimientos financieros en los últimos 6 meses.</p>
            </div>
            @endif
        </section>

        <section class="dash-panel" aria-labelledby="dash-summary-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-summary-title">Resumen General</h2>
                    <p class="dash-panel__desc">Cursos, inscripciones, cultos y docentes</p>
                </div>
            </div>

            <div class="dash-mini">
                <div class="dash-mini__item">
                    <span class="dash-mini__value">{{ number_format($stats['cursos']) }}</span>
                    <span class="dash-mini__label">Cursos</span>
                </div>
                <div class="dash-mini__item">
                    <span class="dash-mini__value">{{ number_format($stats['inscripciones']) }}</span>
                    <span class="dash-mini__label">Inscripciones</span>
                </div>
                <div class="dash-mini__item">
                    <span class="dash-mini__value">{{ number_format($stats['cultos']) }}</span>
                    <span class="dash-mini__label">Cultos</span>
                </div>
                <div class="dash-mini__item">
                    <span class="dash-mini__value">{{ number_format($stats['docentes']) }}</span>
                    <span class="dash-mini__label">Docentes</span>
                </div>
            </div>

            <div class="dash-attendance">
                <h3 class="dash-subtitle">Asistencia general</h3>
                <div class="dash-att">
                    <div class="dash-att__row">
                        <span class="dash-att__name">Presentes</span>
                        <div class="dash-att__track">
                            <div class="dash-att__fill dash-att__fill--ok" style="width: {{ $pctAsist($asistenciaStats['presentes']) }}%"></div>
                        </div>
                        <span class="dash-att__pct">{{ $pctAsist($asistenciaStats['presentes']) }}%</span>
                    </div>
                    <div class="dash-att__row">
                        <span class="dash-att__name">Ausentes</span>
                        <div class="dash-att__track">
                            <div class="dash-att__fill dash-att__fill--no" style="width: {{ $pctAsist($asistenciaStats['ausentes']) }}%"></div>
                        </div>
                        <span class="dash-att__pct">{{ $pctAsist($asistenciaStats['ausentes']) }}%</span>
                    </div>
                    <div class="dash-att__row">
                        <span class="dash-att__name">Tardanzas</span>
                        <div class="dash-att__track">
                            <div class="dash-att__fill dash-att__fill--late" style="width: {{ $pctAsist($asistenciaStats['tardanzas']) }}%"></div>
                        </div>
                        <span class="dash-att__pct">{{ $pctAsist($asistenciaStats['tardanzas']) }}%</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ============ 4. Indicadores clave y alertas ============ --}}
    @if($kpis)
    <div class="dash-grid dash-grid--split">
        <section class="dash-panel" aria-labelledby="dash-kpi-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-kpi-title">Indicadores clave</h2>
                    <p class="dash-panel__desc">Métricas de decisión del período actual</p>
                </div>
            </div>

            <div class="dash-ind-grid">
                @if($kpiMembresia)
                <div class="dash-ind dash-ind--{{ $nivel($kpiMembresia['pct_activos'], 80, 60) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ $kpiMembresia['pct_activos'] !== null ? $kpiMembresia['pct_activos'].'%' : '—' }}</span>
                    <span class="dash-ind__label">Miembros activos</span>
                </div>
                <div class="dash-ind dash-ind--info">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiMembresia['altas_mes']) }}</span>
                    <span class="dash-ind__label">Altas del mes</span>
                </div>
                @endif

                @if($kpiMinisterios)
                <div class="dash-ind dash-ind--{{ $nivel($kpiMinisterios['sin_lider'], 0, 2, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiMinisterios['sin_lider']) }}</span>
                    <span class="dash-ind__label">Ministerios sin líder</span>
                </div>
                <div class="dash-ind dash-ind--{{ $nivel($kpiMinisterios['atrasadas'], 0, 3, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiMinisterios['atrasadas']) }}</span>
                    <span class="dash-ind__label">Actividades atrasadas</span>
                </div>
                @endif

                @if($kpiAcademia)
                <div class="dash-ind dash-ind--{{ $nivel($kpiAcademia['ocupacion_pct'], 70, 50) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ $kpiAcademia['ocupacion_pct'] !== null ? $kpiAcademia['ocupacion_pct'].'%' : '—' }}</span>
                    <span class="dash-ind__label">Ocupación de cursos</span>
                </div>
                <div class="dash-ind dash-ind--{{ $nivel($kpiAcademia['asistencia_pct'], 80, 60) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ $kpiAcademia['asistencia_pct'] !== null ? $kpiAcademia['asistencia_pct'].'%' : '—' }}</span>
                    <span class="dash-ind__label">Asistencia a clases</span>
                </div>
                @endif

                @if($kpiFinanzas)
                <div class="dash-ind dash-ind--{{ $kpiFinanzas['saldo'] >= 0 ? 'ok' : 'no' }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">Bs {{ number_format($kpiFinanzas['saldo'], 2) }}</span>
                    <span class="dash-ind__label">Saldo del mes</span>
                </div>
                <div class="dash-ind dash-ind--{{ $nivel($kpiFinanzas['pct_personal'], 30, 45, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ $kpiFinanzas['pct_personal'] !== null ? $kpiFinanzas['pct_personal'].'%' : '—' }}</span>
                    <span class="dash-ind__label">Gasto en personal</span>
                </div>
                @endif

                @if($kpiCobranza)
                <div class="dash-ind dash-ind--{{ $nivel($kpiCobranza['pct'], 80, 50) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ $kpiCobranza['pct'] !== null ? $kpiCobranza['pct'].'%' : '—' }}</span>
                    <span class="dash-ind__label">Cobranza de cursos</span>
                </div>
                <div class="dash-ind dash-ind--info">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">Bs {{ number_format($kpiCobranza['saldo'], 2) }}</span>
                    <span class="dash-ind__label">Saldo por cobrar</span>
                </div>
                @endif

                @if($kpiPersonal)
                <div class="dash-ind dash-ind--{{ $nivel($kpiPersonal['por_vencer'], 0, 2, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiPersonal['por_vencer']) }}</span>
                    <span class="dash-ind__label">Contratos por vencer</span>
                </div>
                @endif

                @if($kpiSeguridad)
                <div class="dash-ind dash-ind--{{ $nivel($kpiSeguridad['sin_rol'], 0, 0, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiSeguridad['sin_rol']) }}</span>
                    <span class="dash-ind__label">Usuarios sin rol</span>
                </div>
                <div class="dash-ind dash-ind--{{ $nivel($kpiSeguridad['inactivos_30'], 0, 2, false) }}">
                    <span class="dash-ind__dot" aria-hidden="true"></span>
                    <span class="dash-ind__value">{{ number_format($kpiSeguridad['inactivos_30']) }}</span>
                    <span class="dash-ind__label">Usuarios inactivos</span>
                </div>
                @endif
            </div>
        </section>

        <section class="dash-panel" aria-labelledby="dash-alertas-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-alertas-title">Alertas accionables</h2>
                    <p class="dash-panel__desc">Pendientes que requieren seguimiento</p>
                </div>
            </div>

            @if($hayAlertas)
            <div class="dash-alert-list">
                @foreach($alertaContratos as $contrato)
                <div class="dash-alert">
                    <div class="dash-alert__main">
                        <span class="dash-alert__title">Contrato por vencer</span>
                        <span class="dash-alert__meta">{{ $contrato['miembro'] }}</span>
                    </div>
                    <span class="dash-pill dash-pill--warning">{{ $contrato['dias'] }} días</span>
                </div>
                @endforeach

                @foreach($alertaActividades as $actividad)
                <div class="dash-alert">
                    <div class="dash-alert__main">
                        <span class="dash-alert__title">Actividad atrasada</span>
                        <span class="dash-alert__meta">{{ $actividad['nombre'] }}</span>
                    </div>
                    <span class="dash-pill dash-pill--warning">{{ $actividad['avance'] !== null ? $actividad['avance'].'%' : 'Sin avance' }}</span>
                </div>
                @endforeach

                @foreach($alertaCursos as $curso)
                <div class="dash-alert">
                    <div class="dash-alert__main">
                        <span class="dash-alert__title">Cobranza pendiente</span>
                        <span class="dash-alert__meta">{{ $curso['nombre'] }}</span>
                    </div>
                    <span class="dash-pill dash-pill--warning">Bs {{ number_format($curso['saldo'], 2) }}</span>
                </div>
                @endforeach

                @foreach($alertaUsuarios as $usuario)
                <div class="dash-alert">
                    <div class="dash-alert__main">
                        <span class="dash-alert__title">Usuario sin rol</span>
                        <span class="dash-alert__meta">{{ $usuario['name'] }}</span>
                    </div>
                    <span class="dash-pill dash-pill--danger">Sin rol</span>
                </div>
                @endforeach

                @foreach($alertaInactivos as $usuario)
                <div class="dash-alert">
                    <div class="dash-alert__main">
                        <span class="dash-alert__title">Usuario inactivo</span>
                        <span class="dash-alert__meta">{{ $usuario['name'] }}</span>
                    </div>
                    <span class="dash-pill dash-pill--danger">{{ $usuario['last_login_at'] ? \Carbon\Carbon::parse($usuario['last_login_at'])->locale('es')->diffForHumans() : 'Nunca ingresó' }}</span>
                </div>
                @endforeach
            </div>
            @else
            <div class="dash-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <p>Sin alertas pendientes en este período.</p>
            </div>
            @endif
        </section>
    </div>
    @endif

    {{-- ============ 5. Agenda y accesos rápidos ============ --}}
    <div class="dash-grid dash-grid--bottom">
        <section class="dash-panel" aria-labelledby="dash-actividades-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-actividades-title">Próximas actividades</h2>
                </div>
            </div>

            @forelse($proximasActividades as $actividad)
            <div class="dash-row">
                <span class="dash-row__title">{{ $actividad->nombre }}</span>
                <span class="dash-row__date">{{ \Carbon\Carbon::parse($actividad->fecha)->locale('es')->translatedFormat('d M Y') }}</span>
            </div>
            @empty
            <div class="dash-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                <p>No hay actividades programadas próximamente.</p>
            </div>
            @endforelse
        </section>

        <section class="dash-panel" aria-labelledby="dash-sesiones-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-sesiones-title">Próximas sesiones</h2>
                </div>
            </div>

            @forelse($proximasSesiones as $sesion)
            <div class="dash-row">
                <span class="dash-row__title">{{ $sesion->tema }}</span>
                <span class="dash-row__date">{{ \Carbon\Carbon::parse($sesion->fecha)->locale('es')->translatedFormat('d M Y') }}</span>
            </div>
            @empty
            <div class="dash-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <p>No hay sesiones programadas próximamente.</p>
            </div>
            @endforelse
        </section>

        <section class="dash-panel" aria-labelledby="dash-quick-title">
            <div class="dash-panel__head">
                <div>
                    <h2 class="dash-panel__title" id="dash-quick-title">Accesos rápidos</h2>
                </div>
            </div>

            <div class="dash-quick">
                @if($puedeParametrizacion)
                    @if(auth()->user()->puede('miembros', 'crear'))
                    <a class="dash-quick__item" href="{{ route('miembros.create') }}">Nuevo miembro</a>
                    @endif
                    @if(auth()->user()->puede('ministerios', 'crear'))
                    <a class="dash-quick__item" href="{{ route('ministerios.create') }}">Nuevo ministerio</a>
                    @endif
                    @if(auth()->user()->puede('cursos', 'crear'))
                    <a class="dash-quick__item" href="{{ route('cursos.create') }}">Nuevo curso</a>
                    @endif
                    @if(auth()->user()->puede('inscripciones', 'crear'))
                    <a class="dash-quick__item" href="{{ route('inscripciones.index') }}">Nueva inscripción</a>
                    @endif
                    @if(auth()->user()->puede('asistencia', 'crear'))
                    <a class="dash-quick__item" href="{{ route('asistencia.registrar') }}">Registrar asistencia</a>
                    @endif
                @endif
                @if($puedeFinanzas)
                    @if(auth()->user()->puede('ingresos', 'crear'))
                    <a class="dash-quick__item" href="{{ route('ingresos.create') }}">Registrar ingreso</a>
                    @endif
                    @if(auth()->user()->puede('egresos', 'crear'))
                    <a class="dash-quick__item" href="{{ route('egresos.create') }}">Registrar egreso</a>
                    @endif
                    @if(auth()->user()->puede('contratos', 'crear'))
                    <a class="dash-quick__item" href="{{ route('contratos.create') }}">Nuevo contrato</a>
                    @endif
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
