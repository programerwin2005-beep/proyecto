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

    $conceptoIngreso = fn ($ingreso) => trim(($ingreso->tipo?->value ?? 'Sin clasificar') . ($ingreso->metodo_pago ? ' · ' . $ingreso->metodo_pago->value : ''));
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

    <form method="GET" action="{{ route('reportes.financiero') }}" class="report-filters">
        <div class="report-field">
            <label class="form-label" for="filtro-desde">Desde</label>
            <input type="date" name="desde" id="filtro-desde" class="form-control" value="{{ $desde }}">
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-hasta">Hasta</label>
            <input type="date" name="hasta" id="filtro-hasta" class="form-control" value="{{ $hasta }}">
        </div>
        <div class="report-field report-field--action">
            <button type="submit" class="report-btn report-btn--primary">Aplicar filtros</button>
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
