@extends('layouts.app')

@section('title', 'Asignar Roles')

@section('content')
@php
    // Conserva la selección enviada si la validación devuelve al formulario.
    $seleccionados = array_map('intval', (array) old('roles', $user->roles->pluck('id')->all()));
@endphp

<div class="glass-panel" style="padding: 30px; max-width: 760px; margin: 0 auto;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px 20px; margin-bottom: 20px;">
        <div>
            <h2 style="font-weight: 700; color: var(--text-primary);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                Asignar Roles
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">
                Selecciona los roles para <strong style="color: var(--text-primary);">{{ $user->name }}</strong> ({{ $user->email }}).
            </p>
        </div>
        <a href="{{ route('roles.user') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($user->roles->isNotEmpty())
        <div style="margin-bottom: 20px;">
            <span style="color: var(--text-secondary); font-size: 0.85rem; margin-right: 8px;">Roles actuales:</span>
            <span class="role-tags" style="margin-bottom: 0;">
                @foreach($user->roles as $role)
                    <span class="badge badge-info">{{ $role->nombre }}</span>
                @endforeach
            </span>
        </div>
    @endif

    @if(auth()->user()->puede('roles', 'asignar_roles'))
    <form method="POST" action="{{ route('roles.user.save') }}" class="js-assign-roles-form">
        @csrf
        <input type="hidden" name="user_id" value="{{ $user->id }}">

        @if($roles->isEmpty())
            <div class="alert alert-warning">
                No hay roles registrados. Crea un rol antes de asignarlo a un usuario.
            </div>
        @else
            <div class="roles-grid">
                @foreach($roles as $role)
                    <label class="role-option">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="form-check-input" @checked(in_array($role->id, $seleccionados, true))>
                        <span>
                            <span class="role-option__name">{{ $role->nombre }}</span>
                            @if($role->descripcion)
                                <span class="role-option__desc">{{ $role->descripcion }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Roles</button>
            <a href="{{ route('roles.user') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Volver</a>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Evita el envío duplicado del formulario mientras la operación está en curso.
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.js-assign-roles-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function () {
            var boton = form.querySelector('button[type="submit"]');
            if (boton) {
                boton.disabled = true;
                boton.style.opacity = '0.7';
                boton.style.cursor = 'not-allowed';
                boton.textContent = 'Guardando...';
            }
        });
    });
</script>
@endpush
