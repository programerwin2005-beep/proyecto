@extends('layouts.app')

@section('title', 'Administrar Pagos')

@section('content')
<div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
    <div style="flex: 1; min-width: 200px; background: rgba(47,158,68,.12); border: 1px solid rgba(47,158,68,.25); padding: 18px 20px; border-radius: 14px;">
        <div style="font-size: 13px; color: var(--text-secondary);">Total Pagado</div>
        <div style="font-size: 26px; font-weight: 800; color: var(--success);">Bs {{ number_format($totales['total'], 2) }}</div>
    </div>
    <div style="flex: 1; min-width: 200px; background: rgba(37,99,235,.12); border: 1px solid rgba(37,99,235,.25); padding: 18px 20px; border-radius: 14px;">
        <div style="font-size: 13px; color: var(--text-secondary);">Pagos Registrados</div>
        <div style="font-size: 26px; font-weight: 800; color: var(--primary);">{{ $totales['cantidad'] }}</div>
    </div>
</div>

<div class="glass-panel" style="padding: 24px; margin-bottom: 20px;">
    <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--text-primary);">Buscar Pago</h3>
    <form method="GET" action="{{ route('pagos.index') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        @if(auth()->user()->puede('pagos', 'buscar'))
        <div style="flex: 1; min-width: 250px;">
            <label for="q" class="form-label">Miembro o curso</label>
            <input id="q" type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por miembro o curso...">
        </div>
        @endif
        <div>
            <button type="submit" class="btn" style="width: auto;">Filtrar</button>
        </div>
        @if(request()->filled('q'))
        <div>
            <a href="{{ route('pagos.index') }}" class="btn" style="width: auto; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Limpiar</a>
        </div>
        @endif
    </form>
</div>

<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Lista de Pagos</h2>
        @if(auth()->user()->puede('pagos', 'crear'))
        <a href="{{ route('pagos.create') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">+ Nuevo Pago</a>
        @endif
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha de Pago</th>
                    <th>Miembro</th>
                    <th>Curso</th>
                    <th>Monto</th>
                    <th>Método</th>
                    <th>Comprobante</th>
                    <th>Registrado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pagos as $pago)
                    <tr style="@if($pago->trashed()) opacity: 0.5; @endif">
                        <td>{{ $pago->fecha_pago ? \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $pago->inscripcion->miembro->nombre ?? 'N/A' }}</td>
                        <td>{{ $pago->inscripcion->curso->nombre ?? 'N/A' }}</td>
                        <td style="font-weight: 600; color: var(--success);">Bs {{ number_format($pago->monto, 2) }}</td>
                        <td>{{ $pago->metodo_pago?->value ?? 'N/A' }}</td>
                        <td>{{ $pago->comprobante ?: 'N/A' }}</td>
                        <td>{{ $pago->registrador->name ?? 'N/A' }}</td>
                        <td>
                            <div class="action-buttons">
                                @if(auth()->user()->puede('pagos', 'editar'))
                                <a href="{{ route('pagos.edit', $pago->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                @endif
                                @if(auth()->user()->puede('pagos', 'eliminar') && !$pago->inscripcion?->curso?->haFinalizado())
                                <form method="POST" action="{{ route('pagos.destroy', $pago->id) }}" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar este pago?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-delete" title="Eliminar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay pagos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $pagos->links() }}
    </div>
</div>
@endsection
