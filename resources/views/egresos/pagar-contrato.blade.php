@extends('layouts.app')

@section('title', 'Pagar Contrato')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 640px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Pagar Contrato</h2>

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

    @if($contratos->isEmpty())
        <div class="alert alert-danger">No hay contratos activos para pagar.</div>
        <a href="{{ route('egresos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Volver</a>
    @else
    <form method="POST" action="{{ route('egresos.pagar-contrato.store') }}">
        @csrf

        <div class="form-group">
            <label for="id_contrato" class="form-label">Contrato</label>
            <select id="id_contrato" name="id_contrato" class="form-control" required>
                <option value="">Seleccione un contrato...</option>
                @foreach($contratos as $contrato)
                    <option
                        value="{{ $contrato->id }}"
                        data-salario="{{ $contrato->salario }}"
                        {{ (string) old('id_contrato') === (string) $contrato->id ? 'selected' : '' }}
                    >
                        Contrato #{{ $contrato->id }} — {{ $contrato->miembro->nombre ?? $contrato->lider->nombre ?? 'N/A' }}
                        (Bs {{ number_format($contrato->salario, 2) }} · {{ $contrato->vigencia }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="periodo_inicio" class="form-label">Mes a Pagar</label>
            <select id="periodo_inicio" name="periodo_inicio" class="form-control" required>
                <option value="">Seleccione un contrato...</option>
            </select>
            <small id="periodo_detalle" style="display: block; margin-top: 6px; color: var(--text-secondary); font-size: 0.85rem;"></small>
        </div>

        <div class="form-group">
            <label for="monto" class="form-label">Monto a Pagar (Bs)</label>
            <input id="monto" type="number" step="0.01" min="0.01" name="monto" class="form-control" value="{{ old('monto') }}" required autocomplete="off">
            <small style="display: block; margin-top: 6px; color: var(--text-secondary); font-size: 0.85rem;">Puede pagar una parte del mes (cuota); el saldo pendiente seguirá visible.</small>
        </div>

        <div class="form-group">
            <label for="fecha" class="form-label">Fecha</label>
            <input id="fecha" type="text" class="form-control" value="{{ now()->format('d/m/Y') }}" readonly>
            <input type="hidden" name="fecha" value="{{ now()->toDateString() }}">
        </div>

        <div class="form-group">
            <label for="comprobante" class="form-label">Número de Comprobante / Recibo (Opcional)</label>
            <input id="comprobante" type="text" name="comprobante" class="form-control" value="{{ old('comprobante') }}">
        </div>

        <div class="form-group">
            <label for="descripcion" class="form-label">Descripción (Opcional)</label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion') }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Pagar Contrato</button>
            <a href="{{ route('egresos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const contrato = document.getElementById('id_contrato');
        const periodo = document.getElementById('periodo_inicio');
        const detalle = document.getElementById('periodo_detalle');
        const monto = document.getElementById('monto');
        if (!contrato || !periodo || !monto) return;

        const periodos = @json($periodosPorContrato);
        let periodoPrevio = @json(old('periodo_inicio'));

        function actualizarDetalle() {
            const opcion = periodo.options[periodo.selectedIndex];
            const restante = opcion ? parseFloat(opcion.dataset.restante || '0') : 0;

            if (!periodo.value) {
                detalle.textContent = '';
                monto.removeAttribute('max');
                return;
            }

            detalle.textContent = 'Falta por pagar de este mes: Bs ' + restante.toFixed(2);
            monto.max = restante.toFixed(2);
            monto.value = restante.toFixed(2);
        }

        function actualizarPeriodos() {
            const lista = periodos[contrato.value] || [];
            periodo.innerHTML = '';
            detalle.textContent = '';

            if (!lista.length) {
                periodo.innerHTML = '<option value="">Sin meses pendientes</option>';
                detalle.textContent = 'Este contrato no tiene meses pendientes de pago.';
                monto.value = '';
                monto.disabled = true;
                return;
            }

            monto.disabled = false;
            periodo.innerHTML = '<option value="">Seleccione el mes...</option>';

            lista.forEach(function (p) {
                const opcion = document.createElement('option');
                opcion.value = p.inicio;
                opcion.textContent = p.etiqueta + ' (falta Bs ' + Number(p.restante).toFixed(2) + ')';
                opcion.dataset.restante = p.restante;
                if (periodoPrevio === p.inicio) {
                    opcion.selected = true;
                }
                periodo.appendChild(opcion);
            });

            actualizarDetalle();
        }

        contrato.addEventListener('change', function () {
            periodoPrevio = null;
            actualizarPeriodos();
        });

        periodo.addEventListener('change', actualizarDetalle);

        actualizarPeriodos();
    });
</script>
@endsection
