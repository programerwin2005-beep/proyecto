@extends('layouts.app')

@section('title', 'Nuevo Ministerio')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Crear Nuevo Ministerio</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ministerios.store') }}">
        @csrf

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Ministerio</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion') }}</textarea>
        </div>

        <div class="form-group">
            <label for="id_lider_iglesia" class="form-label">Líder Asignado</label>
            <select name="id_lider_iglesia" id="id_lider_iglesia" class="form-control">
                <option value="">Sin líder asignado</option>
                @foreach($lideres as $lider)
                    <option value="{{ $lider->id }}" {{ old('id_lider_iglesia') == $lider->id ? 'selected' : '' }}>
                        {{ $lider->nombre }}{{ $lider->cargo ? ' — '.$lider->cargo->nombre : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Ministerio</button>
            <a href="{{ route('ministerios.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
