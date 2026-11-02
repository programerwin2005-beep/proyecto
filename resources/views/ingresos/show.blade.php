@extends('layouts.app')

@section('title', 'Detalle del Ingreso')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Ingreso</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('ingresos', 'editar'))
            <a href="{{ route('ingresos.edit', $ingreso->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('ingresos.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Monto Total</span>
            <span style="font-weight: 700; color: var(--success);">Bs {{ number_format($ingreso->monto_total, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Tipo</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ingreso->tipo?->value }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Método de Pago</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ingreso->metodo_pago?->value }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Actividad</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ingreso->actividad?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Culto</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ingreso->culto?->nombre ?? 'N/A' }}</span>
        </div>
    </div>
</div>
@endsection
