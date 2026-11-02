@extends('layouts.app')

@section('title', 'Editar Contrato')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Contrato</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contratos.update', $contrato->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="id_miembro" class="form-label">Miembro *</label>
            <select id="id_miembro" name="id_miembro" class="form-control" required>
                <option value="">Seleccione un miembro...</option>
                @foreach($miembros as $miembro)

                    <option value="{{ $miembro->id }}" {{ old('id_miembro', $contrato->id_miembro) == $miembro->id ? 'selected' : '' }}>
                        {{ $miembro->nombre }} — {{ $miembro->email ?? 'Sin email' }}

                    </option>
                
@endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_lider_iglesia" class="form-label">Líder de Iglesia *</label>
            <select name="id_lider_iglesia" id="id_lider_iglesia" class="form-control" required onchange="mostrarCargo()">
                <option value="">Seleccione un líder...</option>
                @foreach($lideres as $lider)

                    <option
                        value="{{ $lider->id }}"
                        data-cargo="{{ $lider->cargo?->nombre ?? 'Sin cargo' }}"
                        {{ old('id_lider_iglesia', $contrato->id_lider_iglesia) == $lider->id ? 'selected' : '' }}

                    >
                        {{ $lider->nombre }}{{ $lider->cargo ? ' (' . $lider->cargo->nombre . ')' : '' }}

                    </option>
                
@endforeach
            </select>
            <p id="cargo-info" style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 5px; min-height: 20px;"></p>
        </div>

        <div class="form-group">
            <label for="tipo_compensacion" class="form-label">Tipo de Compensación *</label>
            <select name="tipo_compensacion" id="tipo_compensacion" class="form-control" required onchange="toggleSalario()">
                @foreach($tiposCompensacion as $valor => $etiqueta)

                    <option value="{{ $valor }}" {{ old('tipo_compensacion', $contrato->tipo_compensacion?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                
@endforeach
            </select>
            <p id="tipo-info" style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 5px; min-height: 20px;"></p>
        </div>

        <div class="form-group">
            <label for="salario" class="form-label">Salario / Monto (Bs) <span id="salario-label">(Requerido)</span></label>
            <input type="number" name="salario" id="salario" class="form-control" step="0.01" min="0" value="{{ old('salario', $contrato->salario) }}">
        </div>

        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                <input id="fecha_inicio" type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', \Carbon\Carbon::parse($contrato->fecha_inicio)->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="fecha_fin" class="form-label">Fecha de Fin (Opcional)</label>
                <input id="fecha_fin" type="date" name="fecha_fin" class="form-control" value="{{ old('fecha_fin', $contrato->fecha_fin ? \Carbon\Carbon::parse($contrato->fecha_fin)->format('Y-m-d') : '') }}">
            </div>
        </div>

        <p style="color: var(--text-secondary); font-size: 0.85rem;">
            La vigencia del contrato se calcula automáticamente según sus fechas.
        </p>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Actualizar Contrato</button>
            <a href="{{ route('contratos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>

<script>
var infoTipos = {
    'Salario': 'El líder recibe un pago mensual por su servicio.',
    'Bono': 'El líder recibe un pago único como bono o compensación especial.',
    'Beneficios': 'El líder recibe beneficios no monetarios (transporte, alimentación, etc.).',
    'Voluntario': 'El líder sirve de forma voluntaria sin compensación económica.'
};

function mostrarCargo() {
    var sel = document.getElementById('id_lider_iglesia');
    var info = document.getElementById('cargo-info');
    var opt = sel.options[sel.selectedIndex];
    if (opt && opt.value && opt.dataset.cargo) {
        info.textContent = 'Cargo asignado: ' + opt.dataset.cargo;
    } else {
        info.textContent = '';
    }
}

function toggleSalario() {
    var tipo = document.getElementById('tipo_compensacion').value;
    var salario = document.getElementById('salario');
    var info = document.getElementById('tipo-info');
    var label = document.getElementById('salario-label');

    info.textContent = infoTipos[tipo] || '';

    if (tipo === 'Voluntario') {
        salario.value = 0;
        salario.disabled = true;
        salario.style.opacity = '0.5';
        label.textContent = '(No aplica)';
    } else if (tipo === 'Bono') {
        salario.disabled = false;
        salario.style.opacity = '1';
        label.textContent = '(Monto del bono)';
    } else if (tipo === 'Beneficios') {
        salario.disabled = false;
        salario.style.opacity = '1';
        label.textContent = '(Valor estimado mensual)';
    } else {
        salario.disabled = false;
        salario.style.opacity = '1';
        label.textContent = '(Requerido)';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarCargo();
    toggleSalario();
});
</script>
@endsection
