@extends('layouts.app')

@section('title', 'Registrar Asistencia')

@section('content')
<div class="glass-panel" style="padding: 24px; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;">
    <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--text-primary);">Seleccionar Sesión</h3>
    <form method="GET" action="{{ route('asistencia.registrar') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label for="filtro_curso" class="form-label">Curso</label>
            <select name="curso_id" id="filtro_curso" class="form-control">
                <option value="">Todos los cursos...</option>
                @foreach($cursos as $curso)
                    <option value="{{ $curso->id }}" {{ request('curso_id') == $curso->id ? 'selected' : '' }}>{{ $curso->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex: 1; min-width: 250px;">
            <label for="select_sesion" class="form-label">Sesión</label>
            <select name="sesion_id" id="select_sesion" class="form-control" required>
                <option value="">Seleccione una sesión...</option>
                @foreach($cursos as $curso)
                    @foreach($curso->sesiones as $sesionCurso)
                        <option value="{{ $sesionCurso->id }}" data-curso-id="{{ $curso->id }}" {{ request('sesion_id') == $sesionCurso->id ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($sesionCurso->fecha)->format('d/m/Y') }} - {{ substr((string) $sesionCurso->hora, 0, 5) }} - {{ $sesionCurso->tema }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="btn" style="width: auto;">Cargar Sesión</button>
        </div>
    </form>
</div>

@if($sesion)
<div class="glass-panel" style="padding: 24px; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h3 style="font-weight: 700; color: var(--text-primary);">{{ $sesion->curso->nombre ?? 'N/A' }}</h3>
            <div style="color: var(--text-secondary); font-size: 14px;">
                {{ \Carbon\Carbon::parse($sesion->fecha)->format('d/m/Y') }} - {{ substr((string) $sesion->hora, 0, 5) }} | {{ $sesion->docente?->miembro?->nombre ?? 'Sin docente' }}
            </div>
            <div style="color: var(--text-secondary); font-size: 14px;"><b>Tema:</b> {{ $sesion->tema }}</div>
        </div>

        @if(auth()->user()->puede('asistencia', 'crear'))
        <form method="POST" action="{{ route('asistencia.marcarTodos', $sesion->id) }}" style="display: inline;" onsubmit="return confirm('¿Marcar a todos los inscritos como presentes?');">
            @csrf
            <button type="submit" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.9rem;">✓ Marcar Todos Presentes</button>
        </form>
        @endif
    </div>

    <div style="display: flex; gap: 16px; margin-top: 16px; flex-wrap: wrap;">
        <div style="background: rgba(47,158,68,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Presente'] }}</b> Presentes</div>
        <div style="background: rgba(220,38,38,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Ausente'] }}</b> Ausentes</div>
        <div style="background: rgba(245,158,11,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Tardanza'] }}</b> Tardanzas</div>
        <div style="background: rgba(99,102,241,.12); padding: 10px 16px; border-radius: 10px;"><b>{{ $resumen['Justificado'] }}</b> Justificados</div>
    </div>
</div>

<div class="glass-panel" style="padding: 30px; max-width: 900px; margin-left: auto; margin-right: auto;">
    <h3 style="font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Registrar Asistencia</h3>

    @if($inscritos->isEmpty())
        <p style="color: var(--text-secondary);">No hay miembros inscritos para esta sesión.</p>
    @else
    @if(auth()->user()->puede('asistencia', 'crear'))
    <form method="POST" action="{{ route('asistencia.store', $sesion->id) }}">
        @csrf

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Miembro</th>
                        <th id="col-estado">Estado</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inscritos as $inscrito)
                        @php
                            $actual = $asistencias->get($inscrito->miembro_id);
                        @endphp
                        <tr>
                            <td>{{ $inscrito->miembro->nombre ?? 'N/A' }}</td>
                            <td>
                                <select name="asistencia[{{ $inscrito->miembro_id }}][estado]" aria-labelledby="col-estado" class="form-control" style="min-width: 140px;">
                                    @foreach(['Presente', 'Ausente', 'Tardanza', 'Justificado'] as $estado)
                                        <option value="{{ $estado }}" {{ ($actual?->estado?->value ?? 'Ausente') === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="asistencia[{{ $inscrito->miembro_id }}][observacion]" aria-label="Observacion de {{ $inscrito->miembro->nombre ?? 'N/A' }}" class="form-control" value="{{ $actual?->observacion ?? '' }}" placeholder="Opcional...">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Asistencia</button>
            <a href="{{ route('asistencia.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
    @endif
    @endif
</div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filtroCurso = document.getElementById('filtro_curso');
        const selectSesion = document.getElementById('select_sesion');
        if (!filtroCurso || !selectSesion) return;

        const PLACEHOLDER = 'Seleccione una sesión...';
        // Snapshot de todas las opciones con su curso
        const todas = Array.from(selectSesion.querySelectorAll('option[data-curso-id]'))
            .map(o => ({ value: o.value, curso: o.dataset.cursoId, texto: o.textContent.trim() }));

        const inicial = selectSesion.value;

        function aplicarFiltro(cursoId) {
            const filtradas = (cursoId === '')
                ? todas
                : todas.filter(o => o.curso === cursoId);

            selectSesion.innerHTML = '';
            const ph = document.createElement('option');
            ph.value = '';
            ph.textContent = PLACEHOLDER;
            selectSesion.appendChild(ph);

            filtradas.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.value;
                opt.dataset.cursoId = o.curso;
                opt.textContent = o.texto;
                selectSesion.appendChild(opt);
            });

            // Mantener selección solo si sigue disponible
            selectSesion.value = filtradas.some(o => o.value === inicial) ? inicial : '';
        }

        filtroCurso.addEventListener('change', () => aplicarFiltro(filtroCurso.value));
        aplicarFiltro(filtroCurso.value);
    });
</script>

@endsection
