@extends('layouts.app')

@section('title', 'Detalle del Culto')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 850px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Culto / Servicio</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('cultos', 'editar'))
            <a href="{{ route('cultos.edit', $culto->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('cultos.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Nombre</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $culto->nombre }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Líder Encargado</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $culto->liderIglesia?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Día</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $culto->dia_semana?->value }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Hora</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ substr($culto->hora, 0, 5) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Descripción</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $culto->descripcion ?? 'N/A' }}</span>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">Ingresos Asociados</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Método de Pago</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($culto->ingresos as $ingreso)

                    <tr>
                        <td>{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $ingreso->tipo?->value }}</td>
                        <td>{{ $ingreso->metodo_pago?->value }}</td>
                        <td style="font-weight: 600;">Bs {{ number_format($ingreso->monto_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            Este culto no tiene ingresos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
