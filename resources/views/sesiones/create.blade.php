@extends('layouts.app')

@section('title', 'Nueva Sesión')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 600px; margin: 0 auto;">
    <h2 style="font-weight: 700; margin-bottom: 20px; color: var(--text-primary);">Registrar Nueva Sesión</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>
                
@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('sesiones.store') }}">
        @csrf

        <div class="form-group">
            <label for="id_curso" class="form-label">Curso</label>
            <select name="id_curso" id="id_curso" class="form-control" required>
                <option value="">Seleccione un curso...</option>
                @foreach($cursos as $curso)

                    <option value="{{ $curso->id }}" {{ old('id_curso') == $curso->id ? 'selected' : '' }}>{{ $curso->nombre }}</option>
                
@endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="id_docente" class="form-label">Docente (Opcional)</label>
            <select name="id_docente" id="id_docente" class="form-control">
                <option value="">Sin docente...</option>
                @foreach($docentes as $docente)

                    <option value="{{ $docente->id }}" {{ old('id_docente') == $docente->id ? 'selected' : '' }}>{{ $docente->miembro->nombre ?? 'Docente #' . $docente->id }}</option>
                
@endforeach
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}" required>
            </div>

            <div class="form-group">
                <label for="hora" class="form-label">Hora</label>
                <input type="time" name="hora" id="hora" class="form-control" value="{{ old('hora') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="tema" class="form-label">Tema</label>
            <input id="tema" type="text" name="tema" class="form-control" value="{{ old('tema') }}" required>
        </div>

        <div class="form-group">
            <label for="observacion" class="form-label">Observación</label>
            <textarea id="observacion" name="observacion" class="form-control" rows="3">{{ old('observacion') }}</textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button type="submit" class="btn">Guardar Sesión</button>
            <a href="{{ route('sesiones.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>

<script>
    const cursosData = @json($cursosData);
    const cursoSelect = document.getElementById('id_curso');
    const fechaInput = document.getElementById('fecha');
    const horaInput = document.getElementById('hora');

    // Devuelve YYYY-MM-DD usando la fecha LOCAL del navegador.
    // No usar toISOString() porque convierte a UTC y desfasa un día.
    function fechaLocalISO(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dia = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dia}`;
    }

    // Los días llegan como texto ("1"), así que comparamos numéricamente
    function diasIncluyen(dias, iso) {
        return (dias || []).map(Number).includes(iso);
    }

    function esDiaPermitido(fecha, cursoId) {
        const data = cursosData[cursoId];
        if (!data || !data.dias_semana || data.dias_semana.length === 0) return true;
        const iso = fecha.getDay() === 0 ? 7 : fecha.getDay();
        return diasIncluyen(data.dias_semana, iso);
    }

    function siguienteFechaPermitida(cursoId) {
        const data = cursosData[cursoId];
        if (!data) return '';
        let fecha = new Date();
        if (data.f_inicio) {
            const ini = new Date(data.f_inicio + 'T00:00:00');
            if (fecha < ini) fecha = new Date(ini);
        }
        for (let i = 0; i < 366; i++) {
            const iso = fecha.getDay() === 0 ? 7 : fecha.getDay();
            if ((!data.dias_semana || data.dias_semana.length === 0 || diasIncluyen(data.dias_semana, iso))
                && (!data.f_fin || fechaLocalISO(fecha) <= data.f_fin)) {
                return fechaLocalISO(fecha);
            }
            fecha.setDate(fecha.getDate() + 1);
        }
        return '';
    }

    function aplicarCurso() {
        const cursoId = cursoSelect.value;
        if (!cursoId) return;
        const data = cursosData[cursoId];

        horaInput.value = data.hora_inicio || '';

        // Limita el selector de fecha al rango del curso
        fechaInput.min = data.f_inicio || '';
        fechaInput.max = data.f_fin || '';
        fechaInput.setCustomValidity('');

        const prox = siguienteFechaPermitida(cursoId);
        if (prox) fechaInput.value = prox;
    }

    cursoSelect.addEventListener('change', aplicarCurso);

    fechaInput.addEventListener('input', function () {
        const cursoId = cursoSelect.value;
        if (!cursoId) return;
        const val = this.value;
        if (!val) return;
        const data = cursosData[cursoId];
        const fecha = new Date(val + 'T00:00:00');
        if (!esDiaPermitido(fecha, cursoId)) {
            this.setCustomValidity('Este curso se dicta los días ' + (data.dias_nombres || '') + '. Elige una fecha dentro del horario asignado.');
        } else if (data.f_inicio && val < data.f_inicio) {
            this.setCustomValidity('La fecha es anterior al inicio del curso.');
        } else if (data.f_fin && val > data.f_fin) {
            this.setCustomValidity('La fecha es posterior al fin del curso.');
        } else {
            this.setCustomValidity('');
        }
    });

    aplicarCurso();
</script>
@endsection
