@extends('layouts.app')

@section('title', 'Editar Rol')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Rol</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('roles.update', $role->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Rol *</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre', $role->nombre) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $role->descripcion) }}</textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="nivel_acceso" class="form-label">Nivel de Acceso *</label>
                <input id="nivel_acceso" type="number" name="nivel_acceso" class="form-control" value="{{ old('nivel_acceso', $role->nivel_acceso) }}" min="1" required>
            </div>

            <div class="form-group">
                <label for="estado" class="form-label">Estado *</label>
                <select id="estado" name="estado" class="form-control" required>
                    <option value="Activo" {{ old('estado', $role->estado) == 'Activo' ? 'selected' : '' }}>Activo</option>
                    <option value="Inactivo" {{ old('estado', $role->estado) == 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="guard_name" class="form-label">Guard Name</label>
            <input id="guard_name" type="text" name="guard_name" class="form-control" value="{{ old('guard_name', $role->guard_name) }}" placeholder="web">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Rol</button>
            <a href="{{ route('roles.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
