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
    if (!empty($filtros['q'])) $meta['Búsqueda'] = $filtros['q'];
    if (!empty($filtros['curso_id'])) $meta['Curso'] = $cursos->find($filtros['curso_id'])?->nombre;
    if (!empty($filtros['ministerio_id'])) $meta['Ministerio'] = $ministerios->find($filtros['ministerio_id'])?->nombre;
    if (!empty($filtros['contratos'])) $meta['Contratos'] = $filtros['contratos'] === 'con' ? 'Con contratos' : 'Sin contratos';
    if (!empty($filtros['ministerios'])) $meta['Participación actual'] = $filtros['ministerios'] === 'con' ? 'Con ministerio' : 'Sin ministerio';

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

    $columnasVinculos = [
        ['label' => 'Miembro'],
        ['label' => 'Cursos e inscripciones'],
        ['label' => 'Contratos'],
        ['label' => 'Ministerio actual'],
    ];
    $filasVinculos = $miembros->map(fn ($miembro) => [
        $miembro->nombre,
        $miembro->cursos->map(fn ($curso) => $curso->nombre . ' (' . $curso->pivot->estado . ')')->implode('; ') ?: 'Sin inscripciones',
        $miembro->contratos->map(fn ($contrato) => '#' . $contrato->id . ' · ' . ($contrato->tipo_compensacion?->value ?? 'Sin tipo') . ' · ' . $contrato->vigencia . ' · ' . $contrato->fecha_inicio . ' al ' . ($contrato->fecha_fin ?: 'sin fecha de fin'))->implode('; ') ?: 'Sin contratos',
        $miembro->ministerio?->nombre ?: 'Sin ministerio actual',
    ])->all();
@endphp

{{-- ============ INTERFAZ DE LA APLICACIÓN (no se imprime) ============ --}}
<div class="glass-panel report-panel">
    <div class="report-panel__head">
        <div>
            <h2 class="report-panel__title">Reportes</h2>
            <p class="report-panel__subtitle">Reporte de miembros</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <form method="GET" action="{{ route('reportes.miembros') }}" class="report-filters">
        <div class="report-field">
            <label class="form-label" for="filtro-q">Buscar miembro</label>
            <input type="search" name="q" id="filtro-q" class="form-control" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre, correo o teléfono">
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-curso">Inscrito en curso</label>
            <select name="curso_id" id="filtro-curso" class="form-control">
                <option value="">Todos</option>
                @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}" @selected(($filtros['curso_id'] ?? '') == $curso->id)>{{ $curso->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-contratos">Contratos registrados</label>
            <select name="contratos" id="filtro-contratos" class="form-control">
                <option value="">Todos</option>
                <option value="con" @selected(($filtros['contratos'] ?? '') === 'con')>Con contratos</option>
                <option value="sin" @selected(($filtros['contratos'] ?? '') === 'sin')>Sin contratos</option>
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-ministerios">Participación actual</label>
            <select name="ministerios" id="filtro-ministerios" class="form-control">
                <option value="">Todos</option>
                <option value="con" @selected(($filtros['ministerios'] ?? '') === 'con')>Con ministerio</option>
                <option value="sin" @selected(($filtros['ministerios'] ?? '') === 'sin')>Sin ministerio</option>
            </select>
        </div>
        <div class="report-field">
            <label class="form-label" for="filtro-ministerio">Ministerio actual</label>
            <select name="ministerio_id" id="filtro-ministerio" class="form-control">
                <option value="">Todos</option>
                @foreach($ministerios as $ministerio)
                    <option value="{{ $ministerio->id }}" @selected(($filtros['ministerio_id'] ?? '') == $ministerio->id)>{{ $ministerio->nombre }}</option>
                @endforeach
            </select>
        </div>
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
        <div class="report-field report-field--action">
            <a href="{{ route('reportes.miembros') }}" class="report-btn">Limpiar filtros</a>
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
    @if(empty($filtros['q']))
        <x-slot:summary>
            <x-report.summary :items="$indicadores" />
        </x-slot:summary>
    @endif

    <x-report.table
        :columns="$columnas"
        :rows="$filas"
        empty="No hay miembros que coincidan con los filtros aplicados."
    />
    <p>Se incluyen inscripciones y contratos registrados no eliminados. El ministerio corresponde a la pertenencia actual del miembro.</p>
    <x-report.table
        :columns="$columnasVinculos"
        :rows="$filasVinculos"
        caption="Cursos, contratos y ministerios por miembro"
        empty="Sin miembros para mostrar."
    />
    @if(!empty($filtros['q']))
        @foreach($miembros as $miembro)
            @if($miembro->docente)
                <h3>{{ $miembro->nombre }} — Docente {{ $miembro->docente->activo ? 'activo' : 'inactivo' }}</h3>
                <p>{{ $miembro->docente->sesiones->count() }} sesiones asignadas pendientes.
                    Se muestran sesiones sin asistencia registrada, incluidas las futuras y las atrasadas.</p>
                @php
                    $columnasSesiones = [
                        ['label' => 'Curso'],
                        ['label' => 'Tema'],
                        ['label' => 'Fecha'],
                        ['label' => 'Hora'],
                        ['label' => 'Estado'],
                    ];
                    $filasSesiones = $miembro->docente->sesiones->map(fn ($sesion) => [
                        $sesion->curso->nombre,
                        $sesion->tema ?: 'Sin tema',
                        \Carbon\Carbon::parse($sesion->fecha)->format('d/m/Y'),
                        $sesion->hora ? substr($sesion->hora, 0, 5) : 'Sin horario',
                        \Carbon\Carbon::parse($sesion->fecha)->startOfDay()->lt(today())
                            ? 'Atrasada'
                            : (\Carbon\Carbon::parse($sesion->fecha)->isToday() ? 'En Progreso' : 'Programado'),
                    ])->all();
                @endphp
                <x-report.table
                    :columns="$columnasSesiones"
                    :rows="$filasSesiones"
                    caption="Sesiones pendientes del docente"
                    empty="No tiene sesiones asignadas pendientes."
                />
            @endif
        @endforeach
    @endif
</x-report.layout>
@endsection
