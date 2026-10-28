@extends('layouts.app')

﻿@section('title', 'Contratos de Personal')

@section('content')
<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Lista de Contratos</h2>
        @if(auth()->user()->puede('contratos', 'crear'))
        <a href="{{ route('contratos.create') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">
            + Nuevo Contrato
        </a>
        @endif
    </div>

    <!-- Filtros de búsqueda -->
    <form method="GET" action="{{ route('contratos.index') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; padding: 15px; border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 25px;">
        @if(auth()->user()->puede('contratos', 'buscar'))
        <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 0;">
            <label for="q" class="form-label">Buscar Miembro</label>
            <input id="q" type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nombre del miembro...">
        </div>
        @endif
        <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
            <label for="tipo_compensacion" class="form-label">Tipo Compensación</label>
            <select id="tipo_compensacion" name="tipo_compensacion" class="form-control">
                <option value="">Todas...</option>
                <option value="Salario" {{ request('tipo_compensacion') === 'Salario' ? 'selected' : '' }}>Salario</option>
                <option value="Bono" {{ request('tipo_compensacion') === 'Bono' ? 'selected' : '' }}>Bono</option>
                <option value="Beneficios" {{ request('tipo_compensacion') === 'Beneficios' ? 'selected' : '' }}>Beneficios</option>
                <option value="Voluntario" {{ request('tipo_compensacion') === 'Voluntario' ? 'selected' : '' }}>Voluntario</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
            <label for="estado" class="form-label">Vigencia</label>
            <select id="estado" name="estado" class="form-control">
                @foreach(['vigente' => 'Vigente', 'por_vencer' => 'Por vencer (30 días)', 'vencido' => 'Vencido', 'futuro' => 'Futuro', 'eliminado' => 'Eliminado'] as $valor => $etiqueta)

                    <option value="{{ $valor }}" {{ request('estado') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                
@endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width: auto; padding: 10px 20px;">Buscar</button>
        @if(request()->filled('q') || request()->filled('estado') || request()->filled('tipo_compensacion'))
            <a href="{{ route('contratos.index') }}" class="btn" style="width: auto; padding: 10px 20px; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Líder Asignado</th>
                    <th>Cargo</th>
                    <th>Tipo</th>
                    <th>Salario</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Vigencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contratos as $contrato)

                    <?php
                        $badgeClase = match($contrato->vigencia) {
                            'Vigente' => 'badge-active',
                            'Por vencer', 'Futuro' => 'badge-warning',
                            default => 'badge-inactive',
                        };
                        $tipoBadge = match($contrato->tipo_compensacion) {
                            'Salario' => 'badge-active',
                            'Bono' => 'badge-info',
                            'Beneficios' => 'badge-warning',
                            'Voluntario' => 'badge-inactive',
                        };
                    ?>
                    <tr style="@if($contrato->trashed()) opacity: 0.5; @endif">
                        <td>{{ $contrato->miembro->nombre ?? 'N/A' }}</td>
                        <td>{{ $contrato->lider?->nombre ?? 'N/A' }}</td>
                        <td>{{ $contrato->lider?->cargo?->nombre ?? 'Sin cargo' }}</td>
                        <td><span class="badge {{ $tipoBadge }}">{{ $contrato->tipo_compensacion }}</span></td>
                        <td style="font-weight: 600;">Bs {{ number_format($contrato->salario, 2) }}</td>
                        <td>{{ \Carbon\Carbon::parse($contrato->fecha_inicio)->format('d/m/Y') }}</td>
                        <td>{{ $contrato->fecha_fin ? \Carbon\Carbon::parse($contrato->fecha_fin)->format('d/m/Y') : 'Indefinido' }}</td>
                        <td>
                            <span class="badge {{ $badgeClase }}">{{ $contrato->vigencia }}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                    <a href="{{ route('contratos.show', $contrato->id) }}" class="btn-icon" title="Ver Detalle"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></a>
                                    @if(auth()->user()->puede('contratos', 'editar'))
                                    <a href="{{ route('contratos.renovar', $contrato->id) }}" class="btn-icon btn-renew" title="Renovar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('contratos', 'editar'))
                                    <a href="{{ route('contratos.edit', $contrato->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('contratos', 'eliminar'))
                                    <form method="POST" action="{{ route('contratos.destroy', $contrato->id) }}" style="display: inline;">
                                        @csrf
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn-icon btn-delete" title="Eliminar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>
                                    </form>
                                    @endif
                            </div>
                        </td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay contratos registrados.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $contratos->links() }}

    </div>
</div>
@endsection
