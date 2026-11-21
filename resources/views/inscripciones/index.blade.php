@extends('layouts.app')

@section('title', 'Administrar Inscripciones')

@section('content')
<div class="glass-panel" style="padding: 24px; margin-bottom: 20px;">
    <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--text-primary);">Buscar Curso</h3>
    <form method="GET" action="{{ route('inscripciones.index') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        @if(auth()->user()->puede('inscripciones', 'buscar'))
        <div style="flex: 1; min-width: 250px;">
            <label for="q" class="form-label">Nombre del curso</label>
            <input id="q" type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por nombre...">
        </div>
        @endif
        <div>
            <label for="tipo" class="form-label">Pago</label>
            <select id="tipo" name="tipo" class="form-control" style="min-width: 160px;">
                <option value="">Todos</option>
                <option value="con_pago" {{ request('tipo') == 'con_pago' ? 'selected' : '' }}>Con costo</option>
                <option value="gratuito" {{ request('tipo') == 'gratuito' ? 'selected' : '' }}>Gratuito</option>
            </select>
        </div>
        <div>
            <label for="cupo" class="form-label">Cupo</label>
            <select id="cupo" name="cupo" class="form-control" style="min-width: 160px;">
                <option value="">Todos</option>
                <option value="disponible" {{ request('cupo') == 'disponible' ? 'selected' : '' }}>Con cupo disponible</option>
                <option value="lleno" {{ request('cupo') == 'lleno' ? 'selected' : '' }}>Cupo lleno</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn" style="width: auto;">Filtrar</button>
        </div>
        @if(request()->has('q') || request()->has('tipo') || request()->has('cupo'))
        <div>
            <a href="{{ route('inscripciones.index') }}" class="btn" style="width: auto; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Limpiar</a>
        </div>
        @endif
    </form>
</div>

<div class="glass-panel" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-weight: 700; color: var(--text-primary);">Cursos e Inscripciones</h2>
    </div>

    <div class="table-container" id="tablaCursos">
        <table class="data-table" id="tblCursos">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th>Inscritos</th>
                    <th>Inscripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cursos as $curso)

                    <tr style="@if($curso->trashed()) opacity: 0.5; @endif">
                        <td>{{ $curso->nombre }}</td>
                        <td>{{ $curso->f_inicio ? \Carbon\Carbon::parse($curso->f_inicio)->format('d/m/Y') : 'N/A' }}</td>
                        <td>{{ $curso->f_fin ? \Carbon\Carbon::parse($curso->f_fin)->format('d/m/Y') : 'N/A' }}</td>
                        <td>
                            @if($curso->trashed())
                                <span class="badge badge-inactive">Eliminado</span>
                            @elseif($curso->haFinalizado())
                                <span class="badge badge-inactive">Cerrado</span>
                            @else
                                <span class="badge badge-active">Abierto</span>
                            @endif
                        </td>
                        <td>{{ $curso->inscripciones_count }} / {{ $curso->cupo_max ?? '∞' }}</td>
                        <td>
                            @if($curso->tiene_pago)
                                <span style="font-weight: 600; color: var(--success);">Bs {{ number_format($curso->monto_inscripcion, 2) }}</span>
                            @else
                                <span class="badge badge-active">Gratuito</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-buttons">
                                @if(auth()->user()->puede('inscripciones', 'crear'))
                                <a href="{{ route('inscripciones.gestion', $curso->id) }}" class="btn-icon" title="{{ $curso->haFinalizado() ? 'Ver inscripciones (curso cerrado)' : 'Inscribirse al curso' }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                
@empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            @if(request()->has('q') || request()->has('tipo') || request()->has('cupo'))
                                No se encontraron cursos con los filtros aplicados.
                            @else
                                No hay cursos registrados.
                            @endif
                        </td>
                    </tr>
                
@endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">
        {{ $cursos->links() }}

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.querySelector('input[name="q"]');
        const tabla = document.getElementById('tblCursos');

        if (!input || !tabla) return;

        const normalizar = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const filas = Array.from(tabla.querySelectorAll('tbody tr'));

        function filtrar() {
            const termino = normalizar(input.value.trim());
            filas.forEach(fila => {
                const nombre = normalizar(fila.cells[0]?.textContent || '');
                fila.style.display = (termino === '' || nombre.includes(termino)) ? '' : 'none';
            });
        }

        input.addEventListener('input', filtrar);
        filtrar();
    });
</script>
@endsection
