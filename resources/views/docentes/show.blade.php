@extends('layouts.app')

@section('title', 'Detalle del Docente')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 850px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Detalle del Docente</h2>
        <div style="display: flex; gap: 10px;">
            @if(!$docente->user && auth()->user()->puede('docentes', 'editar'))
            <a href="{{ route('docentes.acceso', $docente->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Crear acceso</a>
            @endif
            @if(auth()->user()->puede('docentes', 'editar'))
            <a href="{{ route('docentes.edit', $docente->id) }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">Editar</a>
            @endif
            <a href="{{ route('docentes.index') }}" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Volver</a>
        </div>
    </div>

    <div style="border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Miembro</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $docente->miembro?->nombre ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Especialidad</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $docente->especialidad }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Título</span>
            <span style="font-weight: 600; color: var(--text-primary);">{{ $docente->titulo ?? 'N/A' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px;">
            <span style="color: var(--text-secondary);">Estado</span>
            <span style="font-weight: 600; color: var(--text-primary);">
                <span class="badge {{ $docente->activo ? 'badge-active' : 'badge-inactive' }}">{{ $docente->activo ? 'Activo' : 'Inactivo' }}</span>
            </span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-top: 1px solid var(--card-border);">
            <span style="color: var(--text-secondary);">Acceso al sistema</span>
            <span style="font-weight: 600; color: var(--text-primary);">
                @if($docente->user)
                    {{ $docente->user->email }}
                    <span class="badge badge-active" style="margin-left: 6px;">Con acceso</span>
                @else
                    <span class="badge badge-inactive">Sin acceso</span>
                @endif
            </span>
        </div>
    </div>

    <h3 style="font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">Cursos Asignados</h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($docente->cursos as $curso)

                    <tr>
                        <td>{{ $curso->nombre }}</td>
                        <td>{{ \Carbon\Carbon::parse($curso->f_inicio)->format('d/m/Y') }}</td>
                        <td>{{ $curso->f_fin ? \Carbon\Carbon::parse($curso->f_fin)->format('d/m/Y') : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            Este docente no tiene cursos asignados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
