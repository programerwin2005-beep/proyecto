@extends('layouts.app')

@section('title', 'Detalle del Curso')

@section('content')
<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">{{ $curso->nombre }}</h2>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('cursos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Volver</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: rgba(255,255,255,.04); padding: 15px 18px; border-radius: 12px; border: 1px solid var(--card-border);">
            <div style="font-size: 12px; color: var(--text-secondary);">Fechas</div>
            <div>{{ \Carbon\Carbon::parse($curso->f_inicio)->format('d/m/Y') }} - {{ $curso->f_fin ? \Carbon\Carbon::parse($curso->f_fin)->format('d/m/Y') : 'N/A' }}</div>
        </div>
        <div style="background: rgba(255,255,255,.04); padding: 15px 18px; border-radius: 12px; border: 1px solid var(--card-border);">
            <div style="font-size: 12px; color: var(--text-secondary);">Días y Horario</div>
            <div>{{ $curso->dias_nombres ?: 'N/A' }} {{ $curso->tieneHorario() ? '· ' . $curso->rangoHorario() : '' }}</div>
        </div>
        <div style="background: rgba(255,255,255,.04); padding: 15px 18px; border-radius: 12px; border: 1px solid var(--card-border);">
            <div style="font-size: 12px; color: var(--text-secondary);">Cupo</div>
            <div>{{ $curso->cupo_max }} inscritos</div>
        </div>
        <div style="background: rgba(255,255,255,.04); padding: 15px 18px; border-radius: 12px; border: 1px solid var(--card-border);">
            <div style="font-size: 12px; color: var(--text-secondary);">Monto de Inscripción</div>
            <div>@if($curso->tiene_pago) Bs {{ number_format($curso->monto_inscripcion, 2) }} @else <span class="badge badge-active">Gratuito</span> @endif</div>
        </div>
        <div style="background: rgba(255,255,255,.04); padding: 15px 18px; border-radius: 12px; border: 1px solid var(--card-border);">
            <div style="font-size: 12px; color: var(--text-secondary);">Docentes (según sesiones)</div>
            <div>@forelse($docentes as $docente) {{ $docente->miembro->nombre ?? 'Docente' }} @if(!$loop->last), @endif @empty N/A @endforelse</div>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 15px; color: var(--text-primary);">Inscripciones</h3>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Fecha Inscripción</th>
                    <th>Estado</th>
                    <th>Pagado</th>
                    <th>Saldo</th>
                    <th>Estado de Pago</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inscripciones as $inscripcion)
                    <tr style="@if($inscripcion->trashed()) opacity: 0.5; @endif">
                        <td>{{ $inscripcion->miembro->nombre ?? 'N/A' }}</td>
                        <td>{{ \Carbon\Carbon::parse($inscripcion->fecha_inscripcion)->format('d/m/Y') }}</td>
                        <td>
                            @if($inscripcion->estado?->value === 'Activo')
                                <span class="badge badge-active">Activo</span>
                            @elseif($inscripcion->estado?->value === 'Completado')
                                <span class="badge badge-warning">Completado</span>
                            @else
                                <span class="badge badge-inactive">{{ $inscripcion->estado?->value }}</span>
                            @endif
                        </td>
                        <td>Bs {{ number_format($inscripcion->monto_pagado, 2) }}</td>
                        <td>Bs {{ number_format($inscripcion->saldo_pendiente, 2) }}</td>
                        <td>
                            @if($inscripcion->estado_pago == 'Pagado')
                                <span class="badge badge-active">Pagado</span>
                            @elseif($inscripcion->estado_pago == 'Pago Parcial')
                                <span class="badge badge-warning">Pago Parcial</span>
                            @else
                                <span class="badge badge-inactive">Pendiente</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay inscripciones para este curso.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
