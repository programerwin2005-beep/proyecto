@extends('layouts.app')

@section('title', 'Reporte de Miembros')

@section('content')
@php
    // Periodo consultado (texto legible) para el encabezado del documento.
    $periodo = null;
    if ($desde || $hasta) {
        $periodo = ($desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : 'inicio')
            . ' — ' . ($hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : 'actual');
    }

    // Metadatos adicionales del encabezado: solo si existe un filtro real.
    $meta = [];
    if ($estado !== '') {
        $meta['Estado'] = $estado;
    }

    // Indicadores calculados con los MISMOS datos del listado.
    $indicadores = [
        ['label' => 'Total de miembros', 'value' => $totales['total'], 'tone' => 'neutral'],
        ['label' => 'Miembros activos', 'value' => $totales['activos'], 'tone' => 'success'],
        ['label' => 'Miembros inactivos', 'value' => $totales['inactivos'], 'tone' => 'muted'],
    ];

    // Columnas y filas de la tabla (campos reales de la base de datos).
    $columnas = [
        ['label' => 'N.º', 'align' => 'center', 'width' => '48px'],
        ['label' => 'Nombre completo'],
        ['label' => 'Teléfono', 'width' => '120px'],
        ['label' => 'Sexo', 'align' => 'center', 'width' => '80px'],
        ['label' => 'Fecha de ingreso', 'align' => 'center', 'width' => '130px'],
        ['label' => 'Estado', 'align' => 'center', 'width' => '110px'],
    ];

    $filas = $miembros->values()->map(function ($miembro, $index) {
        return [
            $index + 1,
            $miembro->nombre,
            $miembro->telefono ?: '—',
            $miembro->sexo ?: '—',
            $miembro->fecha_ingreso ? \Carbon\Carbon::parse($miembro->fecha_ingreso)->format('d/m/Y') : '—',
            ['badge' => $miembro->estado?->value, 'tone' => $miembro->estado?->value === 'Activo' ? 'success' : 'muted'],
        ];
    })->all();
@endphp

{{-- ============ INTERFAZ DE LA APLICACIÓN (no se imprime) ============ --}}
<div class="glass-panel report-panel">
    <div class="report-panel__head">
        <div>
            <h2 class="report-panel__title">Reportes</h2>
            <p class="report-panel__subtitle">Reporte de miembros</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reportes.miembros') }}" class="report-filters">
        <div class="report-field">
            <label class="form-label" for="filtro-estado">Estado</label>
            <select name="estado" id="filtro-estado" class="form-control">
                <option value="">Todos</option>
                <option value="Activo" {{ $estado == 'Activo' ? 'selected' : '' }}>Activo</option>
                <option value="Inactivo" {{ $estado == 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
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
    title="Reporte de Miembros"
    subtitle="Listado y estado de los miembros de la iglesia"
    :generated-at="now()"
    :period="$periodo"
    :meta="$meta"
>
    <x-slot:summary>
        <x-report.summary :items="$indicadores" />
    </x-slot:summary>

    <x-report.table
        :columns="$columnas"
        :rows="$filas"
        empty="No hay miembros que coincidan con los filtros aplicados."
    />
</x-report.layout>
@endsection
