@extends('layouts.app')

@section('title', 'Detalle del Seguimiento')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Seguimiento</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('seguimiento', 'editar'))
            <a href="{{ route('seguimiento.edit', $seguimiento->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('seguimiento.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Actividad</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $seguimiento->actividad?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha de Registro</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($seguimiento->fecha_registro)->format('d/m/Y') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Responsable</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $seguimiento->responsable }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Estado</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $seguimiento->estado?->value }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Porcentaje de Avance</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ number_format($seguimiento->porcentaje_avance, 0) }}%</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Observación</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $seguimiento->observacion ?? 'N/A' }}</span>
        </div>
    </div>
</div>
@endsection
