@extends('layouts.app')

@section('title', 'Editar Ingreso')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Ingreso</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ingresos.update', $ingreso->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="monto_total" class="form-label">Monto Total (Bs)</label>
            <input id="monto_total" type="number" step="0.01" name="monto_total" class="form-control" value="{{ old('monto_total', $ingreso->monto_total) }}" required autocomplete="off">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha" class="form-label">Fecha</label>
                <input id="fecha" type="date" name="fecha" class="form-control" value="{{ old('fecha', $ingreso->fecha) }}" required>
            </div>

            <div class="form-group">
                <label for="metodo_pago" class="form-label">Método de Pago</label>
                <select id="metodo_pago" name="metodo_pago" class="form-control" required>
                    @foreach(\App\Enums\MetodoPago::opciones() as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ old('metodo_pago', $ingreso->metodo_pago?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="tipo" class="form-label">Tipo de Ingreso</label>
            <select id="tipo" name="tipo" class="form-control" required>
                @foreach(\App\Enums\TipoIngreso::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ old('tipo', $ingreso->tipo?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_actividad" class="form-label">Asociar a Actividad (Opcional)</label>
            <select id="id_actividad" name="id_actividad" class="form-control">
                <option value="">Ninguna...</option>
                @foreach($actividades as $act)
                    <option value="{{ $act->id }}" {{ old('id_actividad', $ingreso->id_actividad) == $act->id ? 'selected' : '' }}>{{ $act->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_culto" class="form-label">Asociar a Culto (Opcional)</label>
            <select id="id_culto" name="id_culto" class="form-control">
                <option value="">Ninguno...</option>
                @foreach($cultos as $c)
                    <option value="{{ $c->id }}" {{ old('id_culto', $ingreso->id_culto) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Ingreso</button>
            <a href="{{ route('ingresos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
