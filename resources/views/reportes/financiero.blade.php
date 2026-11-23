@extends('layouts.app')

@section('title', 'Reporte Financiero')

@section('content')
@php
    $periodo = \Carbon\Carbon::parse($desde)->format('d/m/Y')
        . ' — ' . \Carbon\Carbon::parse($hasta)->format('d/m/Y');

    $indicadores = [
        ['label' => 'Total ingresos', 'value' => 'Bs ' . number_format($totalIngresos, 2), 'tone' => 'success'],
        ['label' => 'Total egresos', 'value' => 'Bs ' . number_format($totalEgresos, 2), 'tone' => 'danger'],
        ['label' => 'Balance', 'value' => 'Bs ' . number_format($balance, 2), 'tone' => $balance >= 0 ? 'info' : 'danger'],
    ];

    $columnasResumen = [
        ['label' => 'Tipo'],
        ['label' => 'Monto', 'align' => 'right', 'width' => '140px'],
    ];

    $filasIngresos = $ingresosPorTipo->map(fn ($total, $tipo) => [
        $tipo,
        'Bs ' . number_format($total, 2),
    ])->values()->all();

    $filasEgresos = $egresosPorTipo->map(fn ($total, $tipo) => [
        $tipo,
        'Bs ' . number_format($total, 2),
    ])->values()->all();

    $conceptoIngreso = fn ($ingreso) => trim(
        ($ingreso->tipo?->value ?? 'Sin clasificar')
        . ($ingreso->metodo_pago ? ' · ' . $ingreso->metodo_pago->value : '')
        . ($ingreso->pago_id ? ' · ' . ($ingreso->pago?->inscripcion?->miembro?->nombre ?? 'Miembro no disponible') : '')
    );
    $conceptoEgreso = fn ($egreso) => trim(($egreso->tipo_egreso?->value ?? 'Sin clasificar') . ($egreso->descripcion ? ' · ' . $egreso->descripcion : ''));

    $columnasMovimientos = [
        ['label' => 'Fecha', 'align' => 'center', 'width' => '110px'],
        ['label' => 'Tipo', 'align' => 'center', 'width' => '90px'],
        ['label' => 'Concepto / Descripción'],
        ['label' => 'Ingreso', 'align' => 'right', 'width' => '130px'],
        ['label' => 'Egreso', 'align' => 'right', 'width' => '130px'],
    ];

    $filasMovimientos = collect()
        ->concat($ingresos->map(fn ($ingreso) => [
            \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y'),
            ['badge' => 'Ingreso', 'tone' => 'success'],
            $conceptoIngreso($ingreso),
            'Bs ' . number_format($ingreso->monto_total, 2),
            '—',
        ]))
        ->concat($egresos->map(fn ($egreso) => [
            \Carbon\Carbon::parse($egreso->fecha)->format('d/m/Y'),
            ['badge' => 'Egreso', 'tone' => 'danger'],
            $conceptoEgreso($egreso),
            '—',
            'Bs ' . number_format($egreso->monto, 2),
        ]))
        ->values()
        ->all();
@endphp

{{-- ============ INTERFAZ DE LA APLICACIÓN (no se imprime) ============ --}}
<div class="glass-panel report-panel">
    <div class="report-panel__head">
        <div>
            <h2 class="report-panel__title">Reportes</h2>
            <p class="report-panel__subtitle">Reporte financiero</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <form method="GET" action="{{ route('reportes.financiero') }}" class="report-filters">
        <div class="report-field">
            <label class="form-label" for="filtro-desde">Desde</label>
            <input type="date" name="desde" id="filtro-desde" class="form-control" value="{{ $desde }}">
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-hasta">Hasta</label>
            <input type="date" name="hasta" id="filtro-hasta" class="form-control" value="{{ $hasta }}">
        </div>
        <div class="report-field">
            <label class="form-label" for="movimiento">Movimientos</label>
            <select id="movimiento" name="movimiento" class="form-control">
                <option value="">Ingresos y egresos</option>
                <option value="ingresos" @selected(($filtros['movimiento'] ?? '') === 'ingresos')>Solo ingresos</option>
                <option value="egresos" @selected(($filtros['movimiento'] ?? '') === 'egresos')>Solo egresos</option>
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="tipo_ingreso">Tipo de ingreso</label>
            <select id="tipo_ingreso" name="tipo_ingreso" class="form-control">
                <option value="">Todos</option>
                @foreach(\App\Enums\TipoIngreso::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['tipo_ingreso'] ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="tipo_egreso">Tipo de egreso</label>
            <select id="tipo_egreso" name="tipo_egreso" class="form-control">
                <option value="">Todos</option>
                @foreach(\App\Enums\TipoEgreso::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['tipo_egreso'] ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="metodo_pago">Método de pago (ingresos)</label>
            <select id="metodo_pago" name="metodo_pago" class="form-control">
                <option value="">Todos</option>
                @foreach(\App\Enums\MetodoPago::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($filtros['metodo_pago'] ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="report-field report-field--action">
            <button type="submit" class="report-btn report-btn--primary">Aplicar filtros</button>
        </div>
        <div class="report-field report-field--action">
            <a href="{{ route('reportes.financiero') }}" class="report-btn">Limpiar filtros</a>
        </div>
    </form>
</div>

<x-report.actions :back-route="route('reportes.index')" />

{{-- ============ DOCUMENTO DEL REPORTE (se imprime / exporta) ============ --}}
<x-report.layout
    title="Reporte Financiero"
    subtitle="Ingresos, egresos y balance del período"
    :generated-at="now()"
    :period="$periodo"
>
    <p>Totales y balance correspondientes a los movimientos filtrados.
        Movimientos: {{ ['ingresos' => 'Solo ingresos', 'egresos' => 'Solo egresos'][$filtros['movimiento'] ?? ''] ?? 'Ingresos y egresos' }}.
        Tipo de ingreso: {{ $filtros['tipo_ingreso'] ?? 'Todos' }}.
        Tipo de egreso: {{ $filtros['tipo_egreso'] ?? 'Todos' }}.
        Método de pago (ingresos): {{ $filtros['metodo_pago'] ?? 'Todos' }}.
    </p>
    <x-slot:summary>
        <x-report.summary :items="$indicadores" />
    </x-slot:summary>

    <x-report.table
        :columns="$columnasResumen"
        :rows="$filasIngresos"
        caption="Ingresos por tipo"
        empty="Sin ingresos registrados en el período."
    />

    <x-report.table
        :columns="$columnasResumen"
        :rows="$filasEgresos"
        caption="Egresos por tipo"
        empty="Sin egresos registrados en el período."
    />

    <x-report.table
        :columns="$columnasMovimientos"
        :rows="$filasMovimientos"
        caption="Detalle de movimientos"
        empty="No hay movimientos registrados en el período seleccionado."
    />
</x-report.layout>
@endsection
