@extends('layouts.app')

@section('title', 'Nuevo Líder de Iglesia')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Nuevo Líder de Iglesia</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('lideres.store') }}">
        @csrf

        <div class="form-group">
            <label for="id_cargo" class="form-label">Cargo</label>
            <select id="id_cargo" name="id_cargo" class="form-control" required>
                <option value="">Seleccione...</option>
                @foreach($cargos as $cargo)

                    <option value="{{ $cargo->id }}" {{ old('id_cargo') == $cargo->id ? 'selected' : '' }}>{{ $cargo->nombre }}</option>
                
@endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre Completo</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="telefono" class="form-label">Teléfono</label>
                <input id="telefono" type="text" name="telefono" class="form-control" value="{{ old('telefono') }}" maxlength="8" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)" placeholder="Máximo 8 números">
            </div>
            
            <div class="form-group">
                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                <input id="fecha_inicio" type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="activo" class="form-label">Estado</label>
            <select id="activo" name="activo" class="form-control" required>
                <option value="1" {{ old('activo', '1') == '1' ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ old('activo') == '0' ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Líder</button>
            <a href="{{ route('lideres.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
