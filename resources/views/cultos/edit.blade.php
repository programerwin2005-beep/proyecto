@extends('layouts.app')

@section('title', 'Editar Culto')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Culto</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cultos.update', $culto->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Culto</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre', $culto->nombre) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="dia_semana" class="form-label">Día de la Semana</label>
            <select id="dia_semana" name="dia_semana" class="form-control" required>
                <option value="">Seleccione un día...</option>
                @foreach(\App\Enums\DiaSemana::opciones() as $dia)
                    <option value="{{ $dia }}" {{ old('dia_semana', $culto->dia_semana?->value) === $dia ? 'selected' : '' }}>{{ $dia }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="hora" class="form-label">Hora</label>
            <input id="hora" type="time" name="hora" class="form-control" value="{{ old('hora', $culto->hora) }}" required>
        </div>

        <div class="form-group">
            <label for="id_lider_iglesia" class="form-label">Líder a Cargo</label>
            <select id="id_lider_iglesia" name="id_lider_iglesia" class="form-control" required>
                <option value="">Seleccione un líder...</option>
                @foreach($lideres as $lider)
                    <option value="{{ $lider->id }}" {{ old('id_lider_iglesia', $culto->id_lider_iglesia) == $lider->id ? 'selected' : '' }}>{{ $lider->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $culto->descripcion) }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Culto</button>
            <a href="{{ route('cultos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
