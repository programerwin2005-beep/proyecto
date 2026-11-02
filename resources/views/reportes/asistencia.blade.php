@extends('layouts.app')

@section('title', 'Reporte de Asistencia')

@section('content')
@php
    $periodo = null;
    if ($desde || $hasta) {
        $periodo = ($desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : 'inicio')
            . ' — ' . ($hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : 'actual');
    }

    $cursoSeleccionado = $cursoId ? $cursos->firstWhere('id', $cursoId) : null;

    $meta = [];
    if ($cursoSeleccionado) {
        $meta['Curso'] = $cursoSeleccionado->nombre;
    }

    $indicadores = [
        ['label' => 'Total de registros', 'value' => $registros->count(), 'tone' => 'neutral'],
        ['label' => 'Presentes', 'value' => $resumen['presentes'], 'tone' => 'success'],
        ['label' => 'Ausentes', 'value' => $resumen['ausentes'], 'tone' => 'danger'],
        ['label' => 'Tardanzas', 'value' => $resumen['tardanzas'], 'tone' => 'warning'],
        ['label' => 'Justificados', 'value' => $resumen['justificados'], 'tone' => 'info'],
    ];

    $tonosEstado = [
        'Presente' => 'success',
        'Ausente' => 'danger',
        'Tardanza' => 'warning',
        'Justificado' => 'info',
    ];

    $columnas = [
        ['label' => 'N.º', 'align' => 'center', 'width' => '48px'],
        ['label' => 'Miembro'],
        ['label' => 'Curso', 'width' => '150px'],
        ['label' => 'Sesión / Tema'],
        ['label' => 'Fecha', 'align' => 'center', 'width' => '110px'],
        ['label' => 'Estado', 'align' => 'center', 'width' => '110px'],
    ];

    $filas = $registros->values()->map(function ($registro, $index) use ($tonosEstado) {
        return [
            $index + 1,
            $registro->miembro->nombre ?? '—',
            $registro->sesion->curso->nombre ?? '—',
            $registro->sesion->tema ?? '—',
            $registro->sesion ? \Carbon\Carbon::parse($registro->sesion->fecha)->format('d/m/Y') : '—',
            ['badge' => $registro->estado?->value, 'tone' => $tonosEstado[$registro->estado?->value] ?? 'info'],
        ];
    })->all();
@endphp

{{-- ============ INTERFAZ DE LA APLICACIÓN (no se imprime) ============ --}}
<div class="glass-panel report-panel">
    <div class="report-panel__head">
        <div>
            <h2 class="report-panel__title">Reportes</h2>
            <p class="report-panel__subtitle">Reporte de asistencia</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reportes.asistencia') }}" class="report-filters">
        <div class="report-field">
            <label class="form-label" for="filtro-curso">Curso</label>
            <select name="curso_id" id="filtro-curso" class="form-control">
                <option value="">Todos los cursos</option>
                @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}" {{ $cursoId == $curso->id ? 'selected' : '' }}>{{ $curso->nombre }}</option>
                @endforeach
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
    title="Reporte de Asistencia"
    subtitle="Resumen de asistencia por curso y rango de fechas"
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
        empty="No hay registros de asistencia que coincidan con los filtros aplicados."
    />
</x-report.layout>
@endsection
