@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Usuario</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('users.update', $user->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name" class="form-label">Nombre *</label>
            <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required autocomplete="name">
        </div>

        <div class="form-group">
            <label for="email" class="form-label">Correo Electrónico *</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Contraseña <span style="font-weight: 400; color: var(--text-secondary);">(dejar en blanco para no cambiar)</span></label>
            <div style="position: relative; display: flex; align-items: center;">
                <input id="password" type="password" class="form-control" name="password" autocomplete="new-password" style="padding-right: 45px;">
                <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('password')" style="position: absolute; right: 15px;">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-2.202-2.202-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="password-confirm" class="form-label">Confirmar Contraseña</label>
            <input id="password-confirm" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Usuario</button>
            <a href="{{ route('users.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
