@extends('layouts.app')

@section('title', 'Nuevo Curso')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Nuevo Curso</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cursos.store') }}">
        @csrf

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Curso</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre') }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="f_inicio" class="form-label">Fecha de Inicio</label>
                <input type="date" name="f_inicio" id="f_inicio" class="form-control" value="{{ old('f_inicio', date('Y-m-d')) }}" required>
            </div>

            <div class="form-group">
                <label for="f_fin" class="form-label">Fecha de Fin</label>
                <input type="date" name="f_fin" id="f_fin" class="form-control" value="{{ old('f_fin') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="cupo_max" class="form-label">Cupo Máximo</label>
            <input id="cupo_max" type="number" name="cupo_max" class="form-control" value="{{ old('cupo_max', 10) }}" min="1" required>
        </div>

        <fieldset class="form-group">
            <legend class="form-label" style="display: block; margin-bottom: 8px;">Días de la Semana</legend>
            <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                @foreach([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $d => $dia)
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="dias_semana[]" value="{{ $d }}" {{ in_array($d, old('dias_semana', [])) ? 'checked' : '' }} style="width: auto;"> {{ $dia }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="hora_inicio" class="form-label">Hora de Inicio</label>
                <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" value="{{ old('hora_inicio') }}">
            </div>

            <div class="form-group">
                <label for="hora_fin" class="form-label">Hora de Fin</label>
                <input type="time" name="hora_fin" id="hora_fin" class="form-control" value="{{ old('hora_fin') }}">
            </div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tiene_pago" value="1" {{ old('tiene_pago') ? 'checked' : '' }} style="width: auto;"> ¿El curso tiene costo de inscripción?
            </label>
        </div>

        <div class="form-group">
            <label for="monto_inscripcion" class="form-label">Monto de Inscripción (Bs)</label>
            <input type="number" step="0.01" min="0" name="monto_inscripcion" id="monto_inscripcion" class="form-control" value="{{ old('monto_inscripcion', 0) }}" required autocomplete="off">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Curso</button>
            <a href="{{ route('cursos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
