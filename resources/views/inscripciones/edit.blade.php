@extends('layouts.app')

@section('title', 'Editar Inscripción')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Inscripción</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;">
        <div style="font-size: 13px; color: var(--text-secondary);">Miembro / Curso</div>
        <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ $inscripcion->miembro->nombre ?? 'N/A' }}</div>
        <div style="font-size: 14px; color: var(--text-secondary);">{{ $inscripcion->curso->nombre ?? 'N/A' }}</div>
    </div>

    <form method="POST" action="{{ route('inscripciones.update', $inscripcion->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="fecha_inscripcion" class="form-label">Fecha de Inscripción</label>
            <input id="fecha_inscripcion" type="date" name="fecha_inscripcion" class="form-control" value="{{ old('fecha_inscripcion', $inscripcion->fecha_inscripcion ? \Carbon\Carbon::parse($inscripcion->fecha_inscripcion)->format('Y-m-d') : '') }}" required>
        </div>

        <div class="form-group">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-control" required>
                @foreach(\App\Enums\EstadoInscripcion::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ old('estado', $inscripcion->estado?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Inscripción</button>
            <a href="{{ route('inscripciones.gestion', $inscripcion->curso_id) }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
