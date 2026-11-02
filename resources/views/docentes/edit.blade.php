@extends('layouts.app')

@section('title', 'Editar Docente')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Docente</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('docentes.update', $docente->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="id_miembro" class="form-label">Miembro</label>
            <select id="id_miembro" name="id_miembro" class="form-control" required>
                <option value="">Seleccione un miembro...</option>
                @foreach($miembros as $m)
                    <option value="{{ $m->id }}" {{ old('id_miembro', $docente->id_miembro) == $m->id ? 'selected' : '' }}>{{ $m->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="especialidad" class="form-label">Especialidad</label>
            <input id="especialidad" type="text" name="especialidad" class="form-control" value="{{ old('especialidad', $docente->especialidad) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="titulo" class="form-label">Título</label>
            <input id="titulo" type="text" name="titulo" class="form-control" value="{{ old('titulo', $docente->titulo) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)">
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $docente->activo) ? 'checked' : '' }} style="width: auto;"> ¿Docente activo?
            </label>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Docente</button>
            <a href="{{ route('docentes.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
