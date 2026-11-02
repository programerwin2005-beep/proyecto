@extends('layouts.app')

@section('title', 'Editar Líder de Iglesia')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Líder de Iglesia</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('lideres.update', $lider->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="id_cargo" class="form-label">Cargo</label>
            <select id="id_cargo" name="id_cargo" class="form-control" required>
                <option value="">Seleccione...</option>
                @foreach($cargos as $cargo)

                    <option value="{{ $cargo->id }}" {{ old('id_cargo', $lider->id_cargo) == $cargo->id ? 'selected' : '' }}>{{ $cargo->nombre }}</option>
                
@endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre Completo</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre', $lider->nombre) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="telefono" class="form-label">Teléfono</label>
                <input id="telefono" type="text" name="telefono" class="form-control" value="{{ old('telefono', $lider->telefono) }}" maxlength="8" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)" placeholder="Máximo 8 números">
            </div>
            
            <div class="form-group">
                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                <input id="fecha_inicio" type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', $lider->fecha_inicio) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="activo" class="form-label">Estado</label>
            <select id="activo" name="activo" class="form-control" required>
                <option value="1" {{ old('activo', $lider->activo) == '1' ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ old('activo', $lider->activo) == '0' ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Líder</button>
            <a href="{{ route('lideres.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
