@extends('layouts.app')

@section('title', 'Editar Egreso')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Egreso</h2>

    <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;">
        <div style="font-size: 13px; color: var(--text-secondary);">Saldo disponible (ingresos - egresos)</div>
        <div style="font-size: 24px; font-weight: 800; color: var(--primary);">Bs {{ number_format($saldoDisponible, 2) }}</div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('egresos.update', $egreso->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="monto" class="form-label">Monto (Bs)</label>
            <input id="monto" type="number" step="0.01" name="monto" class="form-control" value="{{ old('monto', $egreso->monto) }}" required autocomplete="off">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha" class="form-label">Fecha</label>
                <input id="fecha" type="date" name="fecha" class="form-control" value="{{ old('fecha', $egreso->fecha) }}" required>
            </div>

            <div class="form-group">
                <label for="responsable" class="form-label">Responsable del Pago</label>
                <select id="responsable" name="responsable" class="form-control" required>
                    @if($egreso->responsable && !in_array($egreso->responsable, $lideres->pluck('nombre')->all()))
                        <option value="{{ $egreso->responsable }}" selected>{{ $egreso->responsable }}</option>
                    @endif
                    @foreach($lideres as $lider)

                        <option value="{{ $lider->nombre }}" {{ old('responsable', $egreso->responsable) == $lider->nombre ? 'selected' : '' }}>
                            {{ $lider->nombre }}{{ $lider->cargo ? ' - ' . $lider->cargo->nombre : '' }}
                        </option>
                    
@endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="tipo_egreso" class="form-label">Tipo de Egreso</label>
            <select id="tipo_egreso" name="tipo_egreso" class="form-control" required>
                @foreach(\App\Enums\TipoEgreso::opciones() as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ old('tipo_egreso', $egreso->tipo_egreso?->value) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_contrato" class="form-label">Asociar a Contrato de Trabajo (Opcional)</label>
            <select id="id_contrato" name="id_contrato" class="form-control">
                <option value="">Ninguno...</option>
                @foreach($contratos as $c)
                    <option value="{{ $c->id }}" {{ old('id_contrato', $egreso->id_contrato) == $c->id ? 'selected' : '' }}>
                        Contrato #{{ $c->id }} - {{ $c->miembro->nombre ?? 'N/A' }} (Bs {{ number_format($c->salario, 2) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_lider_iglesia" class="form-label">O pagar directamente a un Líder (Opcional)</label>
            <select id="id_lider_iglesia" name="id_lider_iglesia" class="form-control">
                <option value="">Ninguno...</option>
                @foreach($lideres as $lider)
                    <option value="{{ $lider->id }}" {{ old('id_lider_iglesia', $egreso->id_lider_iglesia) == $lider->id ? 'selected' : '' }}>
                        {{ $lider->nombre }}{{ $lider->cargo ? ' - ' . $lider->cargo->nombre : '' }}
                    </option>
                @endforeach
            </select>
            <small style="color: var(--text-secondary); font-size: 12px;">Use contrato o líder directo, no ambos.</small>
        </div>

        <div class="form-group">
            <label for="comprobante" class="form-label">Número de Comprobante / Recibo</label>
            <input id="comprobante" type="text" name="comprobante" class="form-control" value="{{ old('comprobante', $egreso->comprobante) }}">
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción del Egreso</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $egreso->descripcion) }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Egreso</button>
            <a href="{{ route('egresos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const contrato = document.getElementById('id_contrato');
        const lider = document.getElementById('id_lider_iglesia');

        if (!contrato || !lider) return;

        contrato.addEventListener('change', function () {
            if (this.value !== '') lider.value = '';
        });

        lider.addEventListener('change', function () {
            if (this.value !== '') contrato.value = '';
        });
    })();
</script>
@endsection
