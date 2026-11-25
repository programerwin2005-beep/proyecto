@extends('layouts.app')

@section('title', 'Permisos del Rol')

@section('content')
<div class="glass-panel" style="padding: 30px; max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
        <div>
            <h2 style="font-weight: 700; color: var(--text-primary); margin: 0;">Permisos del rol: {{ $role->nombre }}</h2>
            <p style="color: var(--text-secondary); margin: 6px 0 0;">Marca las acciones que este rol puede realizar en cada módulo.</p>
        </div>
        <a href="{{ route('permisos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; width: auto; padding: 8px 16px; font-size: 0.9rem;">← Volver</a>
    </div>

    @if($role->nombre === 'Administrador')
        <div class="alert alert-success" style="margin-bottom: 20px;">
            El rol <b>Administrador</b> siempre tiene acceso total al sistema; estos permisos se muestran solo como referencia.
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('permisos.update', $role->id) }}">
        @csrf
        @method('PUT')

        @foreach($categorias as $categoria => $modulos)
            <div class="glass-panel" style="padding: 18px; margin-bottom: 22px; border: 1px solid var(--card-border);">
                <h3 style="font-weight: 700; color: var(--text-primary); margin: 0 0 14px;">{{ $categoria }}</h3>

                <div class="table-container" style="overflow-x: auto;">
                    <table class="data-table" style="min-width: 640px;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Módulo</th>
                                @foreach(array_diff_key($acciones, config('permisos.especiales')) as $accionKey => $accionLabel)
                                    <th style="text-align: center;">{{ $accionLabel }}</th>
                                @endforeach
                                <th>Acciones específicas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($modulos as $modulo => $meta)
                                @php $permisoActual = $actuales->get($modulo); @endphp
                                <tr>
                                    <td style="font-weight: 600;">{{ $meta['label'] ?? $modulo }}</td>
                                    @foreach(array_diff_key($acciones, config('permisos.especiales')) as $accionKey => $accionLabel)
                                        <td style="text-align: center;">
                                            @if(in_array($accionKey, $meta['acciones'] ?? [], true))
                                                @php
                                                    $marcado = $role->nombre === 'Administrador' || (session()->hasOldInput()
                                                        ? (old('permisos', [])[$modulo][$accionKey] ?? false)
                                                        : (bool) $permisoActual?->{$accionKey});
                                                @endphp
                                                <input type="checkbox"
                                                       name="permisos[{{ $modulo }}][{{ $accionKey }}]"
                                                       value="1"
                                                       aria-label="{{ $meta['label'] ?? $modulo }}: {{ $accionLabel }}"
                                                       style="width: auto; cursor: pointer;"
                                                        @checked($marcado)>
                                            @else
                                                <span style="color: var(--text-secondary);">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @foreach(config('permisos.especiales') as $accionKey => $especial)
                                            @if(in_array($accionKey, $meta['acciones'], true))
                                                @php
                                                    $marcado = $role->nombre === 'Administrador' || (session()->hasOldInput()
                                                        ? (old('permisos', [])[$modulo][$accionKey] ?? false)
                                                        : (bool) $permisoActual?->{$accionKey});
                                                @endphp
                                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; white-space: normal;">
                                                    <input type="checkbox" name="permisos[{{ $modulo }}][{{ $accionKey }}]"
                                                        value="1" style="width: auto;" @checked($marcado)
                                                        aria-label="{{ $meta['label'] }}: {{ $especial['label'] }}">
                                                    {{ $especial['label'] }}
                                                </label>
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div style="display: flex; gap: 15px; margin-top: 10px;">
            <button type="submit" class="btn">Guardar Permisos</button>
            <a href="{{ route('permisos.index') }}" class="btn" style="background: transparent; border: 1px solid var(--card-border); color: var(--text-secondary); text-decoration: none; text-align: center;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
