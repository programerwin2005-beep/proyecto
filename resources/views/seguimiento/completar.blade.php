<form method="POST" action="{{ route('seguimiento.completar', $tarea->id) }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="completada" value="0">
    <label style="display: flex; align-items: center; gap: 8px;">
        <input type="checkbox" name="completada" value="1"
            aria-label="Tarea completada: {{ $tarea->nombre_tarea ?? 'Histórico pendiente de revisión' }}"
            @checked($tarea->estado === \App\Enums\EstadoSeguimiento::Completado)
            @disabled(!auth()->user()->puede('seguimiento', 'editar') || !filled($tarea->nombre_tarea) || $tarea->trashed() || !$tarea->actividad || $tarea->actividad->trashed())
            onchange="this.form.requestSubmit(); this.disabled = true;">
        Tarea completada
    </label>
    @if(!filled($tarea->nombre_tarea))
        <small>Revisar y nombrar esta tarea primero.</small>
    @endif
</form>
