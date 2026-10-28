@extends('layouts.app')

@section('title', 'Crear acceso del docente')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 640px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 8px; color: var(--text-primary);">Crear acceso del docente</h2>
    <p style="color: var(--text-secondary); margin-bottom: 20px;">
        Se creará un usuario con rol <strong>Docente</strong> para
        <strong>{{ $docente->miembro?->nombre ?? 'N/A' }}</strong>. Solo podrá ver sus sesiones y registrar la asistencia de los cursos que tenga asignados.
    </p>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('docentes.acceso.store', $docente->id) }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $docente->miembro?->email) }}" required autocomplete="email">
            <small style="color: var(--text-secondary);">Será el usuario con el que inicie sesión.</small>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Contraseña temporal</label>
            <input id="password" type="password" name="password" class="form-control" required minlength="12" autocomplete="new-password">
            <small style="color: var(--text-secondary);">Mínimo 12 caracteres. El docente podrá cambiarla posteriormente.</small>
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required minlength="12" autocomplete="new-password">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Crear acceso</button>
            <a href="{{ route('docentes.show', $docente->id) }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
