@extends('layouts.app')

@section('title', 'Historial de Asistencia')

@section('content')
<div class="glass-panel" style="padding: 30px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="font-weight: 700; color: var(--text-primary);">Historial de Asistencia</h2>
            <div style="color: var(--text-secondary);">{{ $miembro->nombre }} <span class="badge badge-info">{{ $miembro->estado?->value }}</span></div>
        </div>
        <a href="javascript:history.back()" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">← Volver</a>
    </div>

    @if($resumenPorCurso->isEmpty())
        <p style="color: var(--text-secondary);">No hay registros de asistencia para este miembro.</p>
    @else
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
        @foreach($resumenPorCurso as $cursoNombre => $resumen)

        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Curso</div>
            <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);">{{ $cursoNombre }}</div>
            <div style="margin-top: 8px; font-size: 14px; color: var(--text-secondary);">
                Asistencias: <b>{{ $resumen['presentes'] }}</b> / {{ $resumen['total'] }}
            </div>
            <div style="font-size: 20px; font-weight: 800; color: {{ $resumen['porcentaje'] >= 80 ? 'var(--success)' : ($resumen['porcentaje'] >= 50 ? 'var(--warning)' : 'var(--error)') }};">
                {{ $resumen['porcentaje'] }}%
            </div>
        </div>
        
@endforeach
    </div>
    @endif
</div>

<div class="glass-panel" style="padding: 30px;">
    <h3 style="font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Registros</h3>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Curso</th>
                    <th>Sesión / Tema</th>
                    <th>Estado</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($asistencias as $asistencia)

                    <tr>
                        <td>{{ $asistencia->sesion ? \Carbon\Carbon::parse($asistencia->sesion->fecha)->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $asistencia->sesion?->curso?->nombre ?? 'N/A' }}</td>
                        <td>{{ $asistencia->sesion?->tema ?? 'N/A' }}</td>
                        <td>
                            <span class="badge
                                @if($asistencia->estado?->value === 'Presente') badge-active
                                @elseif($asistencia->estado?->value === 'Ausente') badge-inactive
                                @elseif($asistencia->estado?->value === 'Tardanza') badge-warning
                                @else badge-info
                                @endif">{{ $asistencia->estado?->value }}</span>
                        </td>
                        <td>{{ $asistencia->observacion ?? 'N/A' }}</td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay registros de asistencia.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
