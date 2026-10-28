@extends('layouts.app')

@section('title', 'Administrar Sesiones')

@section('content')
<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Lista de Sesiones</h2>
        @if(auth()->user()->puede('sesiones', 'crear'))
        <a href="{{ route('sesiones.create') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">
            + Nueva Sesión
        </a>
        @endif
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Curso</th>
                    <th>Docente</th>
                    <th>Tema</th>
                    <th>Observación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sesiones as $sesion)

                    <tr style="@if($sesion->trashed()) opacity: 0.5; @endif">
                        <td>{{ \Carbon\Carbon::parse($sesion->fecha)->format('d/m/Y') }}</td>
                        <td>{{ substr((string) $sesion->hora, 0, 5) }}</td>
                        <td>{{ $sesion->curso->nombre ?? 'N/A' }}</td>
                        <td>{{ $sesion->docente?->miembro?->nombre ?? 'N/A' }}</td>
                        <td>{{ $sesion->tema }}</td>
                        <td>{{ $sesion->observacion ?? 'N/A' }}</td>
                        <td>
                            <div class="action-buttons">
                                    <a href="{{ route('sesiones.show', $sesion->id) }}" class="btn-icon" title="Ver"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></a>
                                    @if(auth()->user()->puede('asistencia', 'crear'))
                                    <a href="{{ route('asistencia.registrar', ['sesion_id' => $sesion->id]) }}" class="btn-icon" title="Tomar asistencia"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('sesiones', 'editar'))
                                    <a href="{{ route('sesiones.edit', $sesion->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('sesiones', 'eliminar'))
                                    <form method="POST" action="{{ route('sesiones.destroy', $sesion->id) }}" style="display: inline;">
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
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay sesiones registradas.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $sesiones->links() }}

    </div>
</div>
@endsection
