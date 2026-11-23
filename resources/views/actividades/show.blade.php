@extends('layouts.app')

@section('title', 'Detalle de la Actividad')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 850px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle de la Actividad</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('actividades', 'editar'))
            <a href="{{ route('actividades.edit', $actividad->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('actividades.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Nombre</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $actividad->nombre }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Ministerio</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $actividad->ministerio?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($actividad->fecha)->format('d/m/Y') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Lugar</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $actividad->lugar ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Estado</span>
            <span style="font-weight: 600; color: var(--text-primary);">
                <span class="badge {{ $actividad->estadoVisible() === 'Terminado' ? 'badge-active' : ($actividad->estado?->value === 'Cancelada' ? 'badge-inactive' : 'badge-warning') }}">{{ $actividad->estadoVisible() }}</span>
            </span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Descripción</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $actividad->descripcion ?? 'N/A' }}</span>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">Tareas planificadas</h3>
    <p>{{ $actividad->seguimientos->where('estado', \App\Enums\EstadoSeguimiento::Completado)->count() }} de {{ $actividad->seguimientos->count() }} tareas completadas</p>
    @if(!$actividad->trashed() && auth()->user()->puede('seguimiento', 'crear'))
        <a href="{{ route('seguimiento.create', ['id_actividad' => $actividad->id]) }}" class="btn" style="width: auto; margin-bottom: 15px;">+ Agregar tarea</a>
    @endif
    @if($actividad->seguimientos->contains(fn ($t) => !filled($t->nombre_tarea)))
        <p class="alert alert-warning">Hay seguimientos históricos pendientes de revisión. No se usarán para completar el evento ni calcular su avance hasta confirmar cuáles son tareas independientes y nombrarlas.</p>
    @endif
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha Registro</th>
                    <th>Tarea</th>
                    <th>Responsable</th>
                    <th>Estado</th>
                    <th>Tarea completada</th>
                    <th>Observación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($actividad->seguimientos as $seg)

                    <tr>
                        <td>{{ \Carbon\Carbon::parse($seg->fecha_registro)->format('d/m/Y') }}</td>
                        <td>{{ $seg->nombre_tarea ?? 'Histórico pendiente de revisión' }}</td>
                        <td>{{ $seg->responsable }}</td>
                        <td>{{ $seg->estado?->value }}</td>
                        <td>@include('seguimiento.completar', ['tarea' => $seg])</td>
                        <td>{{ $seg->observacion ?? 'N/A' }}</td>
                        <td>
                            @if(!$actividad->trashed() && auth()->user()->puede('seguimiento', 'editar'))
                                <a href="{{ route('seguimiento.edit', $seg->id) }}" class="btn" style="width: auto;">Actualizar tarea</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            Esta actividad no tiene seguimiento registrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
