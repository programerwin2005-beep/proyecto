@extends('layouts.app')

@section('title', 'Detalle de Sesión')

@section('content')
<div class="glass-panel" style="padding: 30px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle de Sesión</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('asistencia', 'crear'))
            <a href="{{ route('asistencia.registrar', ['sesion_id' => $sesion->id]) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">📋 Registrar Asistencia</a>
            @endif
            @if(auth()->user()->puede('sesiones', 'editar'))
            <a href="{{ route('sesiones.edit', $sesion->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Editar</a>
            @endif
            <a href="{{ route('sesiones.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">← Volver</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Curso</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ $sesion->curso->nombre ?? 'N/A' }}</div>
        </div>
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Docente</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ $sesion->docente?->miembro?->nombre ?? 'N/A' }}</div>
        </div>
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Fecha</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ \Carbon\Carbon::parse($sesion->fecha)->format('d/m/Y') }}</div>
        </div>
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Hora</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ substr((string) $sesion->hora, 0, 5) }}</div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="f_Tema">Tema</label><div id="f_Tema" style="color: var(--text-primary);">
        <div style="padding: 12px 14px; background: rgba(255,255,255,.04); border: 1px solid var(--card-border); border-radius: 10px;">{{ $sesion->tema }}</div>
    </div>

    @if($sesion->observacion)
    <div class="form-group">
        <label class="form-label" for="f_Observación">Observación</label><div id="f_Observación" style="color: var(--text-primary);">
        <div style="padding: 12px 14px; background: rgba(255,255,255,.04); border: 1px solid var(--card-border); border-radius: 10px;">{{ $sesion->observacion }}</div>
    </div>
    @endif

    <div style="display: flex; gap: 16px; margin-top: 24px; flex-wrap: wrap;">
        <div style="background: rgba(47,158,68,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Presente'] }}</b> Presentes</div>
        <div style="background: rgba(220,38,38,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Ausente'] }}</b> Ausentes</div>
        <div style="background: rgba(245,158,11,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Tardanza'] }}</b> Tardanzas</div>
        <div style="background: rgba(99,102,241,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Justificado'] }}</b> Justificados</div>
    </div>
</div>

<div class="glass-panel" style="padding: 30px;">
    <h3 style="font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Inscritos del Curso</h3>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Fecha Inscripción</th>
                    <th>Estado Asistencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inscritos as $inscripcion)

                    @php
                        $asist = $asistencias->get($inscripcion->miembro_id);
                    @endphp
                    <tr>
                        <td>{{ $inscripcion->miembro->nombre ?? 'N/A' }}</td>
                        <td>{{ \Carbon\Carbon::parse($inscripcion->fecha_inscripcion)->format('d/m/Y') }}</td>
                        <td>
                            @if($asist)
                                <span class="badge
                                    @if($asist->estado?->value === 'Presente') badge-active
                                    @elseif($asist->estado?->value === 'Ausente') badge-inactive
                                    @elseif($asist->estado?->value === 'Tardanza') badge-warning
                                    @else badge-info
                                    @endif">{{ $asist->estado?->value }}</span>
                            @else
                                @if($sesionPasada)
                                    <span class="badge badge-inactive">Sin registrar</span>
                                @else
                                    <span class="badge" style="background: rgba(148,163,184,.15); color: #94a3b8;">Pendiente</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay inscritos en este curso.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
