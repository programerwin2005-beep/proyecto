@extends('layouts.app')

@section('title', 'Editar Pago')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 640px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Editar Pago de Inscripción</h2>

    <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;">
        <div style="font-size: 13px; color: var(--text-secondary);">Miembro / Curso</div>
        <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">{{ $pago->inscripcion->miembro->nombre ?? 'N/A' }}</div>
        <div style="font-size: 14px; color: var(--text-secondary);">{{ $pago->inscripcion->curso->nombre ?? 'N/A' }}</div>
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

    <form method="POST" action="{{ route('pagos.update', $pago->id) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="monto" class="form-label">Monto (Bs)</label>
            <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="form-control" value="{{ old('monto', $pago->monto) }}" required autocomplete="off">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                <input id="fecha_pago" type="text" class="form-control" value="{{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') }}" readonly>
                <input type="hidden" name="fecha_pago" value="{{ \Carbon\Carbon::parse($pago->fecha_pago)->format('Y-m-d') }}">
            </div>

            <div class="form-group">
                <label for="metodo_pago" class="form-label">Método de Pago</label>
                <select id="metodo_pago" name="metodo_pago" class="form-control" required>
                    <option value="Efectivo" {{ old('metodo_pago', $pago->metodo_pago?->value) == 'Efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="Transferencia" {{ old('metodo_pago', $pago->metodo_pago?->value) == 'Transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="Tarjeta" {{ old('metodo_pago', $pago->metodo_pago?->value) == 'Tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="comprobante" class="form-label">Comprobante / Recibo (Opcional)</label>
            <input id="comprobante" type="text" name="comprobante" class="form-control" value="{{ old('comprobante', $pago->comprobante) }}">
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Cambios</button>
            <a href="{{ route('pagos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
