@extends('layouts.app')

@section('title', 'Editar Seguimiento')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Seguimiento</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('seguimiento.update', $seguimiento->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="id_actividad" class="form-label">Actividad *</label>
            <select id="id_actividad" name="id_actividad" class="form-control" required>
                <option value="">Seleccione una actividad...</option>
                @foreach($actividades as $actividad)

                    <option value="{{ $actividad->id }}" {{ old('id_actividad', $seguimiento->id_actividad) == $actividad->id ? 'selected' : '' }}>
                        {{ $actividad->nombre }}
                    </option>
                
@endforeach
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha_registro" class="form-label">Fecha de Registro *</label>
                <input id="fecha_registro" type="date" name="fecha_registro" class="form-control" value="{{ old('fecha_registro', \Carbon\Carbon::parse($seguimiento->fecha_registro)->format('Y-m-d')) }}" required>
            </div>

            <div class="form-group">
                <label for="porcentaje_avance" class="form-label">Porcentaje de Avance (%) *</label>
                <input id="porcentaje_avance" type="number" name="porcentaje_avance" class="form-control" value="{{ old('porcentaje_avance', $seguimiento->porcentaje_avance) }}" min="0" max="100" step="1" required>
            </div>
        </div>

        <div class="form-group">
            <label for="responsable" class="form-label">Responsable *</label>
            <input id="responsable" type="text" name="responsable" class="form-control" value="{{ old('responsable', $seguimiento->responsable) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="estado" class="form-label">Estado *</label>
            <select id="estado" name="estado" class="form-control" required>
                @foreach(\App\Enums\EstadoSeguimiento::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ old('estado', $seguimiento->estado?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="observacion" class="form-label">Observación</label>
            <textarea id="observacion" name="observacion" class="form-control" rows="4">{{ old('observacion', $seguimiento->observacion) }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Seguimiento</button>
            <a href="{{ route('seguimiento.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
