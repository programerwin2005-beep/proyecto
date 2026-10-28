@extends('layouts.app')

@section('title', 'Detalle de Usuario')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle de Usuario</h2>
        <div style="display: flex; gap: 10px;">
            @if(!$user->trashed())
                @if(auth()->user()->puede('usuarios', 'editar'))
                <a href="{{ route('users.edit', $user->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
                @endif
            @endif
            <a href="{{ route('users.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
            <span class="badge badge-active">Activo</span>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Nombre</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $user->name }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Correo Electrónico</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $user->email }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Roles</span>
            <span style="font-weight: 600; color: var(--text-primary);">
                @if($user->roles->isNotEmpty())

                        @foreach($user->roles as $role)

                            <span class="badge badge-info">{{ $role->nombre }}</span>
                        
@endforeach
                @else
                    Sin roles
                @endif
            </span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Fecha de Registro</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ \Carbon\Carbon::parse($user->created_at)->format('d/m/Y H:i') }}</span>
        </div>
        @if($user->deleted_at)
            <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
                <span style="color: var(--text-secondary);">Fecha de Eliminación</span>
                <span style="font-weight: 600; color: var(--error);">{{ \Carbon\Carbon::parse($user->deleted_at)->format('d/m/Y H:i') }}</span>
            </div>
        @endif
    </div>

</div>
@endsection
