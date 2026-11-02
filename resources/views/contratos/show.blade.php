@extends('layouts.app')

@section('title', 'Detalle de Contrato')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle de Contrato</h2>
        <div style="display: flex; gap: 10px;">
            @if(!$contrato->trashed())
                @if(auth()->user()->puede('contratos', 'editar'))
                <a href="{{ route('contratos.edit', $contrato->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
                @endif
                @if(auth()->user()->puede('contratos', 'editar'))
                <a href="{{ route('contratos.renovar', $contrato->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Renovar</a>
                @endif
            @endif
            <a href="{{ route('contratos.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
        <?php
            $badgeClase = match($contrato->vigencia) {
                'Vigente' => 'badge-active',
                'Por vencer', 'Futuro' => 'badge-warning',
                default => 'badge-inactive',
            };
            $tipoBadge = match($contrato->tipo_compensacion?->value) {
                'Salario' => 'badge-active',
                'Bono' => 'badge-info',
                'Beneficios' => 'badge-warning',
                'Voluntario' => 'badge-inactive',
            };
        ?>
        <span class="badge {{ $tipoBadge }}">{{ $contrato->tipo_compensacion?->value }}</span>
        <span class="badge {{ $badgeClase }}">{{ $contrato->vigencia }}</span>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Miembro</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $contrato->miembro->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Líder Asignado</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $contrato->lider?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Cargo</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $contrato->lider?->cargo?->nombre ?? 'Sin cargo' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Salario / Monto</span>
            <span style="font-weight: 700; color: var(--success);">Bs {{ number_format($contrato->salario, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha de Inicio</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($contrato->fecha_inicio)->format('d/m/Y') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha de Fin</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $contrato->fecha_fin ? \Carbon\Carbon::parse($contrato->fecha_fin)->format('d/m/Y') : 'Indefinido' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Total Pagado</span>
            <span style="font-weight: 700; color: var(--primary);">Bs {{ number_format($totalPagado, 2) }}</span>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">Egresos Asociados</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo de Egreso</th>
                    <th>Descripción</th>
                    <th>Responsable</th>
                    <th>Comprobante</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contrato->egresos as $egreso)

                    <tr>
                        <td>{{ \Carbon\Carbon::parse($egreso->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $egreso->tipo_egreso?->value }}</td>
                        <td>{{ $egreso->descripcion ?? 'N/A' }}</td>
                        <td>{{ $egreso->responsable }}</td>
                        <td>{{ $egreso->comprobante ?? 'N/A' }}</td>
                        <td style="font-weight: 600;">Bs {{ number_format($egreso->monto, 2) }}</td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            Este contrato no tiene egresos asociados.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
