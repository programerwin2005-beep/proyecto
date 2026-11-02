@extends('layouts.app')

@section('title', 'Detalle de Egreso')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle de Egreso</h2>
        <div style="display: flex; gap: 10px;">
            @if(!$egreso->trashed())
                @if(auth()->user()->puede('egresos', 'editar'))
                <a href="{{ route('egresos.edit', $egreso->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
                @endif
            @endif
            <a href="{{ route('egresos.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Monto</span>
            <span style="font-weight: 700; color: var(--success);">Bs {{ number_format($egreso->monto, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($egreso->fecha)->format('d/m/Y') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Tipo de Egreso</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->tipo_egreso?->value }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Responsable</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->responsable }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Contrato Asociado</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->contrato?->miembro?->nombre ?? 'Ninguno' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Pago Directo a Líder</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->lider?->nombre ?? 'Ninguno' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Comprobante / Recibo</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->comprobante ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Descripción</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $egreso->descripcion ?? 'N/A' }}</span>
        </div>
    </div>
</div>
@endsection
