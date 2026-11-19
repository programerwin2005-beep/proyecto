@extends('layouts.app')

@section('title', 'Registrar Pago')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 640px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Pago de Inscripción</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($cursos->isEmpty())
        <div class="alert alert-danger">No hay inscripciones con saldo pendiente en cursos con costo.</div>
        <a href="{{ route('pagos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Volver</a>
    @else
    <form method="POST" action="{{ route('pagos.store') }}">
        @csrf

        <div class="form-group">
            <label for="curso_id" class="form-label">Curso</label>
            <select id="curso_id" class="form-control" required>
                <option value="">Seleccione un curso...</option>
                @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}">{{ $curso->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="inscripcion_id" class="form-label">Miembro</label>
            <select id="inscripcion_id" name="inscripcion_id" class="form-control" required disabled>
                <option value="">Seleccione primero un curso...</option>
            </select>
        </div>

        <div class="form-group">
            <label for="monto" class="form-label">Monto (Bs)</label>
            <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="form-control" value="{{ old('monto') }}" required autocomplete="off">
            <div id="saldo-hint" style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Seleccione un miembro para ver el saldo pendiente.</div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                <input id="fecha_pago" type="text" class="form-control" value="{{ now()->format('d/m/Y') }}" readonly>
                <input type="hidden" name="fecha_pago" value="{{ now()->toDateString() }}">
            </div>

            <div class="form-group">
                <label for="metodo_pago" class="form-label">Método de Pago</label>
                <select id="metodo_pago" name="metodo_pago" class="form-control" required>
                    <option value="Efectivo" {{ old('metodo_pago') == 'Efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="Transferencia" {{ old('metodo_pago') == 'Transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="Tarjeta" {{ old('metodo_pago') == 'Tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="comprobante" class="form-label">Comprobante / Recibo (Opcional)</label>
            <input id="comprobante" type="text" name="comprobante" class="form-control" value="{{ old('comprobante') }}">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Pago</button>
            <a href="{{ route('pagos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
    @endif
</div>

<script>
    @php
        $datosPagos = $inscripciones->map(fn ($i) => [
            'id' => $i->id,
            'curso_id' => $i->curso_id,
            'miembro' => $i->miembro->nombre ?? 'N/A',
            'saldo' => (float) $i->saldo_pendiente,
        ])->values();
    @endphp

    document.addEventListener('DOMContentLoaded', function () {
        const inscripciones = @json($datosPagos);

        const cursoSelect = document.getElementById('curso_id');
        const miembroSelect = document.getElementById('inscripcion_id');
        const monto = document.getElementById('monto');
        const hint = document.getElementById('saldo-hint');
        if (!cursoSelect || !miembroSelect || !monto || !hint) return;

        const preseleccionada = @json(old('inscripcion_id', $seleccionada));

        function actualizarMonto() {
            const opcion = miembroSelect.options[miembroSelect.selectedIndex];
            const saldo = opcion ? parseFloat(opcion.dataset.saldo || '0') : 0;

            if (!saldo || saldo <= 0) {
                hint.textContent = 'Seleccione un miembro para ver el saldo pendiente.';
                monto.removeAttribute('max');
                return;
            }

            monto.max = saldo.toFixed(2);
            hint.textContent = 'Saldo pendiente: Bs ' + saldo.toFixed(2);
            if (!monto.value) {
                monto.value = saldo.toFixed(2);
            }
        }

        function cargarMiembros(cursoId, seleccionar) {
            miembroSelect.innerHTML = '<option value="">Seleccione un miembro...</option>';

            const delCurso = inscripciones.filter(i => String(i.curso_id) === String(cursoId));

            if (!cursoId || delCurso.length === 0) {
                miembroSelect.disabled = true;
                miembroSelect.innerHTML = '<option value="">No hay miembros con saldo en este curso</option>';
                actualizarMonto();
                return;
            }

            delCurso.forEach(function (i) {
                const option = document.createElement('option');
                option.value = i.id;
                option.dataset.saldo = i.saldo;
                option.textContent = i.miembro + ' (saldo Bs ' + i.saldo.toFixed(2) + ')';
                if (seleccionar && String(i.id) === String(seleccionar)) {
                    option.selected = true;
                }
                miembroSelect.appendChild(option);
            });

            miembroSelect.disabled = false;
            actualizarMonto();
        }

        cursoSelect.addEventListener('change', function () {
            cargarMiembros(this.value, null);
        });
        miembroSelect.addEventListener('change', actualizarMonto);

        // Preselección cuando se llega desde otra vista o tras un error.
        if (preseleccionada) {
            const encontrada = inscripciones.find(i => String(i.id) === String(preseleccionada));
            if (encontrada) {
                cursoSelect.value = encontrada.curso_id;
                cargarMiembros(encontrada.curso_id, encontrada.id);
                return;
            }
        }

        cargarMiembros('', null);
    });
</script>
@endsection
