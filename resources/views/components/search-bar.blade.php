@php($terminoBusqueda = request('q'))
<form method="GET" action="{{ $action }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; padding: 15px; border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 25px;">
    <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
        <label for="q" class="form-label">{{ $label ?? 'Buscar' }}</label>
        <input id="q" type="text" name="q" class="form-control" value="{{ $terminoBusqueda }}" placeholder="{{ $placeholder ?? 'Escribe para buscar...' }}">
    </div>
    <button type="submit" class="btn" style="width: auto; padding: 10px 20px;">Buscar</button>
    @if(request()->filled('q'))
        <a href="{{ $action }}" class="btn" style="width: auto; padding: 10px 20px; background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none;">Limpiar</a>
    @endif
</form>
