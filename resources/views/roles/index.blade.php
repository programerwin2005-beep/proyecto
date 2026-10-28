@extends('layouts.app')

@section('title', 'Roles')

@section('content')
<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Lista de Roles</h2>
        @if(auth()->user()->puede('roles', 'crear'))
        <a href="{{ route('roles.create') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">
            + Nuevo Rol
        </a>
        @endif
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Nivel de Acceso</th>
                    <th>Guard</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)

                    <tr style="@if($role->trashed()) opacity: 0.5; @endif">
                        <td style="font-weight: 600;">{{ $role->nombre }}</td>
                        <td>{{ $role->descripcion ?? 'N/A' }}</td>
                        <td>{{ $role->nivel_acceso }}</td>
                        <td>{{ $role->guard_name }}</td>
                        <td>
                            @if($role->estado == 'Activo')
                                <span class="badge badge-active">Activo</span>
                            @else
                                <span class="badge badge-inactive">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-buttons">
                                    @if(auth()->user()->puede('roles', 'editar'))
                                    <a href="{{ route('roles.edit', $role->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('roles', 'eliminar'))
                                    <form method="POST" action="{{ route('roles.destroy', $role->id) }}" style="display: inline;">
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
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay roles registrados.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $roles->links() }}

    </div>
</div>
@endsection
