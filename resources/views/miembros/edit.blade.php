@extends('layouts.app')

@section('title', 'Editar Miembro')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Miembro</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('miembros.update', $miembro->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre Completo</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $miembro->nombre) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $miembro->email) }}">
            </div>
            
            <div class="form-group">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" name="telefono" id="telefono" class="form-control" value="{{ old('telefono', $miembro->telefono) }}" maxlength="8" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)" placeholder="Máximo 8 números">
            </div>
        </div>

        <div class="form-group">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" name="direccion" id="direccion" class="form-control" value="{{ old('direccion', $miembro->direccion) }}">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="f_nacimiento" class="form-label">Fecha de Nacimiento</label>
                <input type="date" name="f_nacimiento" id="f_nacimiento" class="form-control" value="{{ old('f_nacimiento', $miembro->f_nacimiento) }}">
            </div>
            
            <div class="form-group">
                <label for="sexo" class="form-label">Sexo</label>
                <select name="sexo" id="sexo" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Masculino" {{ old('sexo', $miembro->sexo) == 'Masculino' ? 'selected' : '' }}>Masculino</option>
                    <option value="Femenino" {{ old('sexo', $miembro->sexo) == 'Femenino' ? 'selected' : '' }}>Femenino</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha_ingreso" class="form-label">Fecha de Ingreso</label>
                <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control" value="{{ old('fecha_ingreso', $miembro->fecha_ingreso) }}" required>
            </div>
            
            <div class="form-group">
                <label for="estado" class="form-label">Estado</label>
                <select name="estado" id="estado" class="form-control" required>
                    @foreach(\App\Enums\EstadoMiembro::opciones() as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ old('estado', $miembro->estado?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Miembro</button>
            <a href="{{ route('miembros.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
