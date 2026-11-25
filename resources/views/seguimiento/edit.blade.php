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
        @if(!filled($seguimiento->nombre_tarea))
            <p class="alert alert-warning">Registro histórico pendiente de revisión. Antes de nombrarlo, confirme que representa una tarea independiente. Si es un avance anterior de otra tarea, elimínelo de las tareas vigentes y actualice la tarea actual en lugar de duplicarla. La eliminación conserva el registro y sus observaciones.</p>
        @endif
        <div class="form-group">
            <label for="nombre_tarea" class="form-label">Nombre de la tarea *</label>
            <input id="nombre_tarea" name="nombre_tarea" class="form-control" maxlength="255" value="{{ old('nombre_tarea', $seguimiento->nombre_tarea) }}" required>
        </div>

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

        </div>

        @php
            $actividadSeleccionada = (string) old('id_actividad', $seguimiento->id_actividad);
            $responsableSeleccionado = old('responsable', $seguimiento->responsable);
            $responsablesActuales = $actividades
                ->firstWhere('id', (int) $actividadSeleccionada)
                ?->nombresResponsables() ?? collect();
        @endphp

        <div class="form-group">
            <label for="responsable" class="form-label">Responsable *</label>
            <select id="responsable" name="responsable" class="form-control" required>
                <option value="">Seleccione un responsable...</option>
                @if($responsableSeleccionado && ! $responsablesActuales->contains($responsableSeleccionado))
                    <option value="{{ $responsableSeleccionado }}" data-actividad="{{ $actividadSeleccionada }}" selected>
                        {{ $responsableSeleccionado }} (responsable actual)
                    </option>
                @endif
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
