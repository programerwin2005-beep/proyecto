@extends('layouts.app')

@section('title', 'Asignar Rol')

@section('content')
<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px 20px; margin-bottom: 20px;">
        <div>
            <h2 style="font-weight: 700; color: var(--text-primary);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                Asignar Rol
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">Gestiona los roles asignados a los usuarios del sistema.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('roles.user') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; padding: 15px; border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 25px;">
        <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
            <label for="q" class="form-label">Buscar usuario</label>
            <input id="q" type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nombre o correo electrónico...">
        </div>
        <button type="submit" class="btn" style="width: auto; padding: 10px 20px;">Buscar</button>
        @if(request()->filled('q'))
            <a href="{{ route('roles.user') }}" class="btn" style="width: auto; padding: 10px 20px; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo Electrónico</th>
                    <th>Roles Actuales</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td style="font-weight: 600;">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if($user->roles->isNotEmpty())
                                <div class="role-tags" style="margin-bottom: 0;">
                                    @foreach($user->roles as $role)
                                        <span class="badge badge-info">{{ $role->nombre }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-secondary);">Sin roles</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-buttons">
                                @if(auth()->user()->puede('roles', 'asignar_roles'))
                                <a href="{{ route('roles.user.edit', $user->id) }}" class="btn-icon btn-edit" title="Asignar roles" aria-label="Asignar roles a {{ $user->name }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                </a>
                                @endif
                                @if(auth()->user()->puede('roles', 'asignar_roles'))
                                <form method="POST" action="{{ route('roles.user.clear', $user->id) }}" style="display: inline-flex;" onsubmit="return confirm('¿Quitar todos los roles asignados a este usuario?');">
                                    @csrf
                                    <button type="submit" class="btn-icon btn-delete" title="Limpiar roles" aria-label="Limpiar roles de {{ $user->name }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            @if(request()->filled('q'))
                                No se encontraron usuarios con ese nombre o correo.
                            @else
                                No hay usuarios registrados.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $users->links() }}
    </div>
</div>
@endsection
