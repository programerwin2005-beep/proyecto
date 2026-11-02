@extends('layouts.app')

@section('title', 'Nuevo Cargo')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Nuevo Cargo</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cargos.store') }}">
        @csrf

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Cargo</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre') }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="nivel_jerarquico" class="form-label">Nivel Jerárquico</label>
            <input id="nivel_jerarquico" type="number" name="nivel_jerarquico" class="form-control" value="{{ old('nivel_jerarquico', 1) }}" min="1" required>
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion') }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Cargo</button>
            <a href="{{ route('cargos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
