@extends('layouts.app')

@section('title', 'Detalle del Miembro')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Miembro</h2>
        @if($miembro->trashed())
            <span class="badge badge-inactive">Eliminado</span>
        @elseif($miembro->estado?->value === 'Activo')
            <span class="badge badge-active">Activo</span>
        @else
            <span class="badge badge-inactive">Inactivo</span>
        @endif
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label class="form-label" for="f_Nombre Completo">Nombre Completo</label>
            <div style="color: var(--text-primary);">{{ $miembro->nombre }}</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Correo Electrónico">Correo Electrónico</label>
            <div style="color: var(--text-primary);">{{ $miembro->email ?? 'N/A' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Teléfono">Teléfono</label>
            <div style="color: var(--text-primary);">{{ $miembro->telefono ?? 'N/A' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Dirección">Dirección</label>
            <div style="color: var(--text-primary);">{{ $miembro->direccion ?? 'N/A' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Fecha de Nacimiento">Fecha de Nacimiento</label>
            <div style="color: var(--text-primary);">
                {{ $miembro->f_nacimiento ? \Carbon\Carbon::parse($miembro->f_nacimiento)->format('d/m/Y') : 'N/A' }}
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Sexo">Sexo</label>
            <div style="color: var(--text-primary);">{{ $miembro->sexo ?? 'N/A' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Fecha de Ingreso">Fecha de Ingreso</label>
            <div style="color: var(--text-primary);">
                {{ $miembro->fecha_ingreso ? \Carbon\Carbon::parse($miembro->fecha_ingreso)->format('d/m/Y') : 'N/A' }}
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_Estado">Estado</label>
            <div style="color: var(--text-primary);">{{ $miembro->estado?->value }}</div>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-top: 30px;">
        @if(auth()->user()->puede('miembros', 'editar'))
        <a href="{{ route('miembros.edit', $miembro->id) }}" class="btn" style="text-decoration: none; text-align: center;">Editar</a>
        @endif
        <a href="{{ route('miembros.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Volver</a>
    </div>
</div>
@endsection
