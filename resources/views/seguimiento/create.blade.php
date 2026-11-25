@extends('layouts.app')

@section('title', 'Nuevo Seguimiento')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Nuevo Seguimiento</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('seguimiento.store') }}">
        @csrf
        <div class="form-group">
            <label for="nombre_tarea" class="form-label">Nombre de la tarea *</label>
            <input id="nombre_tarea" name="nombre_tarea" class="form-control" maxlength="255" value="{{ old('nombre_tarea') }}" required>
        </div>

        <div class="form-group">
            <label for="id_actividad" class="form-label">Actividad *</label>
            <select id="id_actividad" name="id_actividad" class="form-control" required>
                <option value="">Seleccione una actividad...</option>
                @foreach($actividades as $actividad)

                    <option value="{{ $actividad->id }}" {{ old('id_actividad', request('id_actividad')) == $actividad->id ? 'selected' : '' }}>
                        {{ $actividad->nombre }}
                    </option>
                
@endforeach
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha_registro" class="form-label">Fecha de Registro *</label>
                <input id="fecha_registro" type="date" name="fecha_registro" class="form-control" value="{{ old('fecha_registro', date('Y-m-d')) }}" required>
            </div>

        </div>

        @php
            $actividadSeleccionada = (string) old('id_actividad', request('id_actividad'));
            $responsableSeleccionado = old('responsable');
        @endphp

        <div class="form-group">
            <label for="responsable" class="form-label">Responsable *</label>
            <select id="responsable" name="responsable" class="form-control" required>
                <option value="">Seleccione un responsable...</option>
                @foreach($actividades as $actividad)
                    @foreach($actividad->nombresResponsables() as $nombre)
                        <option value="{{ $nombre }}"
                            data-actividad="{{ $actividad->id }}"
                            {{ $actividadSeleccionada === (string) $actividad->id && $responsableSeleccionado === $nombre ? 'selected' : '' }}>
                            {{ $nombre }} — {{ $actividad->ministerio?->nombre }}
                        </option>
                    @endforeach
                @endforeach
            </select>
            <small style="color: var(--text-secondary);">Solo se listan los miembros y el líder del ministerio que organiza la actividad.</small>
        </div>

        <div class="form-group">
            <label for="estado" class="form-label">Estado *</label>
            <select id="estado" name="estado" class="form-control" required>
                <option value="Pendiente" {{ old('estado', 'Pendiente') == 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="En Progreso" {{ old('estado') == 'En Progreso' ? 'selected' : '' }}>En Progreso</option>
                <option value="Completado" {{ old('estado') == 'Completado' ? 'selected' : '' }}>Completado</option>
                <option value="Detenido" {{ old('estado') == 'Detenido' ? 'selected' : '' }}>Detenido</option>
            </select>
        </div>

        <div class="form-group">
            <label for="observacion" class="form-label">Observación</label>
            <textarea id="observacion" name="observacion" class="form-control" rows="4">{{ old('observacion') }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Seguimiento</button>
            <a href="{{ route('seguimiento.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const actividad = document.getElementById('id_actividad');
    const responsable = document.getElementById('responsable');

    if (!actividad || !responsable) {
        return;
    }

    const opciones = Array.from(responsable.querySelectorAll('option[data-actividad]'));

    const filtrar = function () {
        const seleccionada = actividad.value;
        let conservaSeleccion = false;

        opciones.forEach(function (opcion) {
            const corresponde = opcion.dataset.actividad === seleccionada;
            opcion.hidden = !corresponde;
            opcion.disabled = !corresponde;

            if (corresponde && opcion.selected) {
                conservaSeleccion = true;
            }
        });

        if (!conservaSeleccion) {
            responsable.value = '';
        }
    };

    actividad.addEventListener('change', filtrar);
    filtrar();
});
</script>
@endpush
