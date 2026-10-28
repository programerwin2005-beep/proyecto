@extends('layouts.app')

@section('title', 'Detalle del Ministerio')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 850px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Ministerio</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('ministerios', 'editar'))
            <a href="{{ route('ministerios.edit', $ministerio->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('ministerios.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Nombre</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ministerio->nombre }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Líder</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ministerio->lider?->nombre ?? 'Sin asignar' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Cargo del Líder</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ministerio->lider?->cargo?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Descripción</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $ministerio->descripcion ?? 'N/A' }}</span>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">Miembros del Ministerio</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Fecha de Inicio</th>
                    <th>Fecha de Fin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ministerio->miembros as $miembro)

                    <tr>
                        <td>{{ $miembro->nombre }}</td>
                        <td>{{ \Carbon\Carbon::parse($miembro->pivot->fecha_inicio)->format('d/m/Y') }}</td>
                        <td>{{ $miembro->pivot->fecha_fin ? \Carbon\Carbon::parse($miembro->pivot->fecha_fin)->format('d/m/Y') : 'Actualidad' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            Este ministerio no tiene miembros asignados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
