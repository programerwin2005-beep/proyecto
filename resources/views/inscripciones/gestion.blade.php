@extends('layouts.app')

@section('title', 'Gestionar Inscripciones')

@section('content')
<div class="glass-panel" style="padding: 30px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">{{ $curso->nombre }}</h2>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->puede('pagos', 'ver'))
            <a href="{{ route('pagos.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Pagos</a>
            @endif
            <a href="{{ route('inscripciones.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">← Volver</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Periodo</div>
            <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);">
                {{ $curso->f_inicio ? \Carbon\Carbon::parse($curso->f_inicio)->format('d/m/Y') : 'N/A' }} - {{ $curso->f_fin ? \Carbon\Carbon::parse($curso->f_fin)->format('d/m/Y') : 'N/A' }}
            </div>
        </div>
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Inscritos</div>
            <div style="font-size: 16px; font-weight: 700; color: var(--text-primary);">{{ $inscripciones->whereNull('deleted_at')->count() }} / {{ $curso->cupo_max ?? '∞' }}</div>
        </div>
        @if($curso->tiene_pago)
        <div style="background: rgba(47,158,68,.12); border: 1px solid rgba(47,158,68,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Total Pagado</div>
            <div style="font-size: 16px; font-weight: 700; color: var(--success);">Bs {{ number_format($totalPagado, 2) }}</div>
        </div>
        <div style="background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.25); padding: 16px 18px; border-radius: 12px;">
            <div style="font-size: 13px; color: var(--text-secondary);">Esperado</div>
            <div style="font-size: 16px; font-weight: 700; color: var(--warning);">Bs {{ number_format($esperado, 2) }}</div>
        </div>
        @endif
    </div>

    @if($curso->tiene_pago)
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 10px 16px; border-radius: 10px;">Costo inscripción: <b>Bs {{ number_format($curso->monto_inscripcion, 2) }}</b></div>
        @if($curso->tieneHorario())
        <div style="background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.25); padding: 10px 16px; border-radius: 10px;">Horario: <b>{{ $curso->dias_nombres }} {{ $curso->rangoHorario() }}</b></div>
        @endif
    </div>
    @endif
</div>

@if($errors->any())
    <div class="alert alert-danger" style="max-width: 900px; margin: 0 auto 20px;">
        <ul>
            @foreach($errors->all() as $error)

                <li>{{ $error }}</li>
            
@endforeach
        </ul>
    </div>
@endif

@if($curso->haFinalizado())
<div class="alert alert-danger" style="max-width: 900px; margin: 0 auto 20px;">
    El curso ya finalizó; no se pueden registrar nuevas inscripciones.
</div>
@endif

@if(!$curso->trashed() && !$curso->haFinalizado())
<div class="glass-panel" style="padding: 24px; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;">
    <h3 style="font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Inscribir Miembro</h3>

    @if(auth()->user()->puede('inscripciones', 'crear'))
    <form method="POST" action="{{ route('inscripciones.store', $curso->id) }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        @csrf
        <div style="flex: 1; min-width: 250px;">
            <label for="miembro_id" class="form-label">Miembro</label>
            <select id="miembro_id" name="miembro_id" class="form-control" required>
                <option value="">Seleccione un miembro...</option>
                @foreach($miembrosDisponibles as $miembro)

                    <option value="{{ $miembro->id }}" {{ old('miembro_id') == $miembro->id ? 'selected' : '' }}>{{ $miembro->nombre }}</option>
                
@endforeach
            </select>
        </div>
        <div>
            <label for="fecha_inscripcion" class="form-label">Fecha de Inscripción</label>
            <input id="fecha_inscripcion" type="date" name="fecha_inscripcion" class="form-control" value="{{ old('fecha_inscripcion', date('Y-m-d')) }}" required>
        </div>
        <div>
            <button type="submit" class="btn" style="width: auto;">+ Inscribir</button>
        </div>
    </form>
    @endif
</div>
@endif

<div class="glass-panel" style="padding: 30px; max-width: 900px; margin-left: auto; margin-right: auto;">
    <h3 style="font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Lista de Inscripciones</h3>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Fecha Inscripción</th>
                    <th>Estado</th>
                    @if($curso->tiene_pago)
                    <th>Pago</th>
                    @endif
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inscripciones as $inscripcion)

                    <tr style="@if($inscripcion->trashed()) opacity: 0.5; @endif">
                        <td>
                            {{ $inscripcion->miembro->nombre ?? 'N/A' }}
                            @if($inscripcion->trashed())
                                <span class="badge badge-inactive">Eliminado</span>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($inscripcion->fecha_inscripcion)->format('d/m/Y') }}</td>
                        <td>
                            @if($inscripcion->estado?->value === 'Activo')
                                <span class="badge badge-active">Activo</span>
                            @elseif($inscripcion->estado?->value === 'Completado')
                                <span class="badge badge-info">{{ $inscripcion->estado?->value }}</span>
                            @else
                                <span class="badge badge-inactive">{{ $inscripcion->estado?->value }}</span>
                            @endif
                        </td>
                        @if($curso->tiene_pago)
                        <td>
                            @if($inscripcion->estado_pago == 'Pagado')
                                <span class="badge badge-active">Pagado</span>
                            @elseif($inscripcion->estado_pago == 'Pago Parcial')
                                <span class="badge badge-warning">Pago Parcial</span>
                                <div style="font-size: 12px; color: var(--text-secondary);">Bs {{ number_format($inscripcion->saldo_pendiente, 2) }}</div>
                            @else
                                <span class="badge badge-inactive">Pendiente</span>
                            @endif
                        </td>
                        @endif
                        <td>
                            <div class="action-buttons">
                                    @if(auth()->user()->puede('inscripciones', 'editar'))
                                    <a href="{{ route('inscripciones.edit', $inscripcion->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('inscripciones', 'eliminar') && !$curso->haFinalizado())
                                    @php
                                        $tienePagos = $inscripcion->pagos->whereNull('deleted_at')->isNotEmpty();
                                        $mensajeEliminar = $tienePagos
                                            ? 'Esta inscripción tiene pagos. ¿Está seguro de eliminarla? También se eliminarán sus pagos e ingresos.'
                                            : '¿Está seguro de eliminar esta inscripción?';
                                    @endphp
                                    <form method="POST" action="{{ route('inscripciones.destroy', $inscripcion->id) }}" style="display: inline;" onsubmit="return confirm(@js($mensajeEliminar));">
                                        @csrf
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn-icon btn-delete" title="Eliminar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>
                                    </form>
                                    @endif
                            </div>
                        </td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="{{ $curso->tiene_pago ? 5 : 4 }}" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No hay inscripciones para este curso.
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
