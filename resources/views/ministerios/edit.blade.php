@extends('layouts.app')

@section('title', 'Editar Ministerio')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Ministerio</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ministerios.update', $ministerio->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="nombre" class="form-label">Nombre del Ministerio</label>
            <input id="nombre" type="text" name="nombre" class="form-control" value="{{ old('nombre', $ministerio->nombre) }}" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" title="Solo letras y espacios" oninput="soloLetras(this)" required>
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $ministerio->descripcion) }}</textarea>
        </div>

        <div class="form-group">
            <label for="id_lider_iglesia" class="form-label">Líder Asignado</label>
            <select name="id_lider_iglesia" id="id_lider_iglesia" class="form-control">
                <option value="">Sin líder asignado</option>
                @foreach($lideres as $lider)
                    <option value="{{ $lider->id }}" {{ old('id_lider_iglesia', $ministerio->id_lider_iglesia) == $lider->id ? 'selected' : '' }}>
                        {{ $lider->nombre }}{{ $lider->cargo ? ' — '.$lider->cargo->nombre : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Miembros del Ministerio</label>
            <input type="hidden" name="miembros_presente" value="1">
            <div style="max-height: 260px; overflow-y: auto; border: 1px solid var(--card-border); border-radius: 8px; padding: 10px 14px;">
                @forelse($miembros as $miembro)
                    @php
                        $marcado = in_array($miembro->id, array_map('intval', (array) $seleccionados), true);
                        $inactivo = $miembro->estado !== \App\Enums\EstadoMiembro::Activo;
                    @endphp
                    <label style="display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer;">
                        <input type="checkbox" name="miembros[]" value="{{ $miembro->id }}"
                            {{ $marcado ? 'checked' : '' }}
                            {{ $inactivo && ! $marcado ? 'disabled' : '' }}>
                        <span>
                            {{ $miembro->nombre }}
                            @if($inactivo)
                                <em style="color: var(--text-secondary); font-size: 12px;">(Inactivo)</em>
                            @endif
                        </span>
                    </label>
                @empty
                    <p style="color: var(--text-secondary); margin: 0;">No hay miembros registrados.</p>
                @endforelse
            </div>
            <small style="color: var(--text-secondary); font-size: 12px;">
                La composición se guarda únicamente en el pivote miembro_ministerio. El líder se asigna aparte y no se duplica como miembro.
            </small>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Ministerio</button>
            <a href="{{ route('ministerios.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
