@extends('layouts.app')

@section('title', 'Gestionar Inscripciones')

@section('content')
<div class="glass-panel" style="padding: 30px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">{{ $curso->nombre }}</h2>
        <a href="{{ route('inscripciones.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">← Volver</a>
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

@if(!$curso->trashed())
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
                            @if($inscripcion->estado == 'Activo')
                                <span class="badge badge-active">Activo</span>
                            @elseif($inscripcion->estado == 'Completado')
                                <span class="badge badge-info">{{ $inscripcion->estado }}</span>
                            @else
                                <span class="badge badge-inactive">{{ $inscripcion->estado }}</span>
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
                                    @if($curso->tiene_pago && $inscripcion->saldo_pendiente > 0)
                                    @if(auth()->user()->puede('inscripciones', 'crear'))
                                    <a href="{{ route('pagos.create', $inscripcion->id) }}" class="btn-icon" title="Registrar Pago"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg></a>
                                    @endif
                                    @endif
                                    @if(auth()->user()->puede('inscripciones', 'editar'))
                                    <a href="{{ route('inscripciones.edit', $inscripcion->id) }}" class="btn-icon btn-edit" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 20.082a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>
                                    @endif
                                    @if(auth()->user()->puede('inscripciones', 'eliminar'))
                                    <form method="POST" action="{{ route('inscripciones.destroy', $inscripcion->id) }}" style="display: inline;">
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
