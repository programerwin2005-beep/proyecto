<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel') - Iglesia Actúa</title>
    <script>
        if (localStorage.getItem('actua_theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <?php
        $accesoActive = Request::is('users*') || Request::is('roles*');
        $paramActive = Request::is('miembros*') || Request::is('cultos*') || Request::is('actividades*') || Request::is('cursos*') || Request::is('cargos*') || Request::is('lideres*') || Request::is('ministerios*') || Request::is('docentes*') || Request::is('seguimiento*') || Request::is('contratos*');
        $transacActive = Request::is('inscripciones*') || Request::is('asistencia*') || Request::is('sesiones*') || Request::is('ingresos*') || Request::is('egresos*');
    ?>

    <div class="app-container">
        <script>
            // Restaura el estado del menú ANTES de pintar el sidebar (evita el parpadeo).
            (function () {
                try {
                    if (window.matchMedia('(min-width: 769px)').matches
                        && localStorage.getItem('actua_sidebar') === 'collapsed') {
                        document.currentScript.parentElement.classList.add('sidebar-collapsed');
                    }
                } catch (e) { /* almacenamiento no disponible */ }
            })();
        </script>

        <!-- Sidebar Navigation -->
        <aside class="sidebar glass-panel" id="app-sidebar" aria-label="Menú de navegación">
            <div class="sidebar-header">
                <button type="button" id="sidebar-toggle" class="sidebar-toggle" onclick="toggleSidebar()" title="Contraer menú" aria-label="Contraer o expandir menú" aria-controls="app-sidebar" aria-expanded="true">
                    <svg class="icon-panel" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18" stroke-linecap="round"/></svg>
                    <svg class="icon-close" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <div class="logo-container">
                    <span class="logo-mark" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25v5.25M9.75 5.25h4.5M6 21.75V11.25l6-3.75 6 3.75v10.5M9.75 21.75v-5.25h4.5v5.25"/></svg>
                    </span>
                    <span class="logo-text">Actúa Iglesia</span>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item {{ Route::is('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" data-title="Principal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                        <span>Principal</span>
                    </a>
                </li>

                <!-- Acceso y Seguridad -->
                @if(auth()->user()->puede('usuarios', 'ver') || auth()->user()->puede('roles', 'ver') || auth()->user()->puede('permisos', 'ver'))
                <li class="nav-category">
                    <button type="button" class="nav-category-header {{ $accesoActive ? 'expanded' : '' }}" data-title="Acceso y Seguridad" aria-controls="submenu-acceso" aria-expanded="{{ $accesoActive ? 'true' : 'false' }}" onclick="toggleSubmenu('submenu-acceso', this)">
                        <span class="cat-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg><span class="cat-label">Acceso y Seguridad</span></span>
                        <span class="caret">▸</span>
                    </button>
                    <ul class="submenu {{ $accesoActive ? 'expanded' : '' }}" id="submenu-acceso">
                        @if(auth()->user()->puede('usuarios', 'ver'))
                        <li class="nav-item {{ Request::is('users*') ? 'active' : '' }}">
                            <a href="{{ route('users.index') }}" data-title="Usuarios"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg><span>Administrar Usuarios</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('roles', 'ver'))
                        <li class="nav-item {{ Request::is('roles') || Request::is('roles/create') || Request::is('roles/*/edit') ? 'active' : '' }}">
                            <a href="{{ route('roles.index') }}" data-title="Roles"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/></svg><span>Administrar Roles</span></a>
                        </li>
                        <li class="nav-item {{ Request::is('roles/assign*') ? 'active' : '' }}">
                            <a href="{{ route('roles.user') }}" data-title="Rol Usuario"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg><span>Administrar Rol Usuario</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('permisos', 'ver'))
                        <li class="nav-item {{ Request::is('permisos*') ? 'active' : '' }}">
                            <a href="{{ route('permisos.index') }}" data-title="Permisos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"/></svg><span>Administrar Permisos</span></a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Parametrización -->
                @if(auth()->user()->puede('miembros', 'ver') || auth()->user()->puede('cargos', 'ver') || auth()->user()->puede('lideres', 'ver') || auth()->user()->puede('ministerios', 'ver') || auth()->user()->puede('actividades', 'ver') || auth()->user()->puede('seguimiento', 'ver') || auth()->user()->puede('cursos', 'ver') || auth()->user()->puede('docentes', 'ver') || auth()->user()->puede('contratos', 'ver') || auth()->user()->puede('cultos', 'ver'))
                <li class="nav-category">
                    <button type="button" class="nav-category-header {{ $paramActive ? 'expanded' : '' }}" data-title="Parametrización" aria-controls="submenu-param" aria-expanded="{{ $paramActive ? 'true' : 'false' }}" onclick="toggleSubmenu('submenu-param', this)">
                        <span class="cat-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.142-.854-.108-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.781-.929l.149-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg><span class="cat-label">Parametrización</span></span>
                        <span class="caret">▸</span>
                    </button>
                    <ul class="submenu {{ $paramActive ? 'expanded' : '' }}" id="submenu-param">
                        @if(auth()->user()->puede('miembros', 'ver'))
                        <li class="nav-item {{ Request::is('miembros*') ? 'active' : '' }}">
                            <a href="{{ route('miembros.index') }}" data-title="Miembros"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg><span>Administrar Miembro</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('cultos', 'ver'))
                        <li class="nav-item {{ Request::is('cultos*') ? 'active' : '' }}">
                            <a href="{{ route('cultos.index') }}" data-title="Cultos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/></svg><span>Administrar Culto</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('actividades', 'ver'))
                        <li class="nav-item {{ Request::is('actividades*') ? 'active' : '' }}">
                            <a href="{{ route('actividades.index') }}" data-title="Actividades"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 2.994v2.25m10.5-2.25v2.25m-14.252 13.5V7.491a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v11.251m-18 0a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5m-6.75-6h2.25m-9 2.25h4.5m.002-2.25h.005v.006H12V13.5Zm6.004 0h.005v.006h-.005V13.5Zm-6.004-2.247h.005v.005h-.005v-.005Zm9 2.247h.005v.006h-.005V13.5Zm-3.006-2.247h.006v.005h-.006v-.005Z"/></svg><span>Administrar Actividad</span></a>
                        </li>
                        <li class="nav-item {{ Request::is('cursos*') ? 'active' : '' }}">
                            <a href="{{ route('cursos.index') }}" data-title="Cursos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg><span>Administrar Curso</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('cargos', 'ver'))
                        <li class="nav-item {{ Request::is('cargos*') ? 'active' : '' }}">
                            <a href="{{ route('cargos.index') }}" data-title="Cargos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"/></svg><span>Administrar Cargo</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('lideres', 'ver'))
                        <li class="nav-item {{ Request::is('lideres*') ? 'active' : '' }}">
                            <a href="{{ route('lideres.index') }}" data-title="Líderes"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"/></svg><span>Administrar Líderes Iglesia</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('ministerios', 'ver'))
                        <li class="nav-item {{ Request::is('ministerios*') ? 'active' : '' }}">
                            <a href="{{ route('ministerios.index') }}" data-title="Ministerios"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg><span>Administrar Ministerio</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('docentes', 'ver'))
                        <li class="nav-item {{ Request::is('docentes*') ? 'active' : '' }}">
                            <a href="{{ route('docentes.index') }}" data-title="Docentes"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.409 60.44 60.44 0 0 0-.492-6.31m-15.48 0a48.667 48.667 0 0 1 7.148-.474m-7.148 3.334a48.668 48.668 0 0 1 7.148-.474m0 0a48.667 48.667 0 0 1 7.148.474m-7.148-3.334A48.667 48.667 0 0 1 19.02 9.84m0 0A48.622 48.622 0 0 1 21.42 15.25M18 5.25a48.715 48.715 0 0 0-12 0v3.375m12-3.375a48.716 48.716 0 0 0 0 3.375M12 9.375a48.667 48.667 0 0 1 0 6.375M12 15.75a48.667 48.667 0 0 1-6 0"/></svg><span>Administrar Docentes</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('seguimiento', 'ver'))
                        <li class="nav-item {{ Request::is('seguimiento*') ? 'active' : '' }}">
                            <a href="{{ route('seguimiento.index') }}" data-title="Seguimiento"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg><span>Administrar Seguimiento</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('contratos', 'ver'))
                        <li class="nav-item {{ Request::is('contratos*') ? 'active' : '' }}">
                            <a href="{{ route('contratos.index') }}" data-title="Contratos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg><span>Administrar Contratos</span></a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                <!-- Transaccional -->
                @if(auth()->user()->puede('sesiones', 'ver') || auth()->user()->puede('inscripciones', 'ver') || auth()->user()->puede('asistencia', 'ver') || auth()->user()->puede('ingresos', 'ver') || auth()->user()->puede('egresos', 'ver'))
                <li class="nav-category">
                    <button type="button" class="nav-category-header {{ $transacActive ? 'expanded' : '' }}" data-title="Transaccional" aria-controls="submenu-transac" aria-expanded="{{ $transacActive ? 'true' : 'false' }}" onclick="toggleSubmenu('submenu-transac', this)">
                        <span class="cat-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg><span class="cat-label">Transaccional</span></span>
                        <span class="caret">▸</span>
                    </button>
                    <ul class="submenu {{ $transacActive ? 'expanded' : '' }}" id="submenu-transac">
                        @if(auth()->user()->puede('sesiones', 'ver'))
                        <li class="nav-item {{ Request::is('sesiones*') ? 'active' : '' }}">
                            <a href="{{ route('sesiones.index') }}" data-title="Sesiones"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg><span>Administrar Sesiones</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('inscripciones', 'ver'))
                        <li class="nav-item {{ Request::is('inscripciones*') ? 'active' : '' }}">
                            <a href="{{ route('inscripciones.index') }}" data-title="Inscripciones"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg><span>Administrar Inscripciones</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('asistencia', 'ver'))
                        <li class="nav-item {{ Request::is('asistencia*') ? 'active' : '' }}">
                            <a href="{{ route('asistencia.index') }}" data-title="Asistencia"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3"/></svg><span>Administrar Asistencia</span></a>
                        </li>
                        @endif
                        @if(auth()->user()->puede('ingresos', 'ver') || auth()->user()->puede('egresos', 'ver'))
                        <li class="nav-item {{ Request::is('ingresos*') ? 'active' : '' }}">
                            <a href="{{ route('ingresos.index') }}" data-title="Ingresos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg><span>Administrar Ingresos</span></a>
                        </li>
                        <li class="nav-item {{ Request::is('egresos*') ? 'active' : '' }}">
                            <a href="{{ route('egresos.index') }}" data-title="Egresos"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-18 6.75h18M2.25 6.75a1.5 1.5 0 0 1 1.5-1.5h16.5a1.5 1.5 0 0 1 1.5 1.5v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6.75Z"/></svg><span>Administrar Egresos</span></a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

        <?php
            $reporteActive = Request::is('reportes*');
        ?>
                @if(auth()->user()->puede('reportes.miembros', 'ver') || auth()->user()->puede('reportes.asistencia', 'ver') || auth()->user()->puede('reportes.financiero', 'ver'))
                <li class="nav-category">
                    <button type="button" class="nav-category-header {{ $reporteActive ? 'expanded' : '' }}" data-title="Reporte" aria-controls="submenu-reporte" aria-expanded="{{ $reporteActive ? 'true' : 'false' }}" onclick="toggleSubmenu('submenu-reporte', this)">
                        <span class="cat-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg><span class="cat-label">Reporte</span></span>
                        <span class="caret">▸</span>
                    </button>
                    <ul class="submenu {{ $reporteActive ? 'expanded' : '' }}" id="submenu-reporte">
                        <li class="nav-item {{ Request::is('reportes*') ? 'active' : '' }}">
                            <a href="{{ route('reportes.index') }}" data-title="Reportes"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg><span>Administrar Reportes</span></a>
                        </li>
                    </ul>
                </li>
                @endif
            </ul>

            <div class="sidebar-user-wrap" id="user-menu-wrap">
                <button class="sidebar-user" type="button" onclick="toggleUserMenu()" aria-label="Opciones de cuenta" aria-haspopup="true" aria-controls="user-menu" aria-expanded="false">
                    <div class="user-avatar">{{ mb_strtoupper(mb_substr(trim(Auth::user()->name), 0, 2)) }}</div>
                    <span class="sidebar-user-name">{{ Auth::user()->name }}</span>
                    <svg class="user-caret" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div class="user-menu" id="user-menu" role="menu" aria-label="Cuenta de usuario">
                    <form method="POST" action="{{ route('logout') }}" role="presentation">
                        @csrf
                        <button type="submit" class="user-menu-item" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- Header -->
            <header class="header glass-panel" style="margin: 15px 15px 0 15px; border-radius: 15px;">
                <div style="display: flex; align-items: center;">
                    <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Abrir menú" aria-controls="app-sidebar" aria-expanded="false">☰</button>
                    <div class="header-title"></div>
                </div>
                <button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema" aria-label="Cambiar tema">
                    <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                    <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                </button>
            </header>

            <!-- Main view content -->
            <div class="content-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}

                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}

                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Mobile Sidebar Drawer Overlay -->
    <button type="button" class="sidebar-overlay" id="sidebar-overlay" aria-label="Cerrar menú" onclick="toggleSidebar()"></button>

    <script>
        function toggleSubmenu(id, header) {
            const container = document.querySelector('.app-container');
            const submenu = document.getElementById(id);

            // Si el menú está colapsado: expandirlo y abrir la categoría clicada
            if (container.classList.contains('sidebar-collapsed')) {
                container.classList.remove('sidebar-collapsed');
                persistSidebarState();
                updateSidebarA11y();
                if (!submenu.classList.contains('expanded')) {
                    submenu.style.maxHeight = 'none';
                    const height = submenu.scrollHeight;
                    submenu.style.maxHeight = '0px';
                    submenu.offsetHeight; // Force repaint
                    submenu.classList.add('expanded');
                    header.classList.add('expanded');
                    header.setAttribute('aria-expanded', 'true');
                    submenu.style.maxHeight = height + 'px';
                }
                return;
            }

            const isExpanded = submenu.classList.contains('expanded');
            
            if (isExpanded) {
                // Collapsing: set current height first so transition works
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                submenu.offsetHeight; // Force repaint
                
                submenu.classList.remove('expanded');
                header.classList.remove('expanded');
                header.setAttribute('aria-expanded', 'false');
                submenu.style.maxHeight = '0px';
            } else {
                // Expanding: measure height by temporarily setting max-height to none
                submenu.style.maxHeight = 'none';
                const height = submenu.scrollHeight;
                submenu.style.maxHeight = '0px';
                submenu.offsetHeight; // Force repaint
                
                submenu.classList.add('expanded');
                header.classList.add('expanded');
                header.setAttribute('aria-expanded', 'true');
                submenu.style.maxHeight = height + 'px';
            }
        }

        const SIDEBAR_KEY = 'actua_sidebar';

        function isMobileView() {
            return window.innerWidth <= 768;
        }

        // Guarda la preferencia del menú reutilizando localStorage (igual que el tema).
        function persistSidebarState() {
            try {
                const collapsed = document.querySelector('.app-container').classList.contains('sidebar-collapsed');
                localStorage.setItem(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
            } catch (e) { /* almacenamiento no disponible */ }
        }

        function updateSidebarA11y() {
            const toggle = document.getElementById('sidebar-toggle');
            const hamburger = document.querySelector('.hamburger-btn');

            if (isMobileView()) {
                const open = document.querySelector('.sidebar').classList.contains('open');
                if (toggle) {
                    toggle.setAttribute('aria-label', 'Cerrar menú');
                    toggle.setAttribute('title', 'Cerrar menú');
                }
                if (hamburger) {
                    hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            } else {
                const collapsed = document.querySelector('.app-container').classList.contains('sidebar-collapsed');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    toggle.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
                    toggle.setAttribute('title', collapsed ? 'Expandir menú' : 'Contraer menú');
                }
                if (hamburger) {
                    hamburger.setAttribute('aria-expanded', 'false');
                }
            }
        }

        // Abre/cierra el panel lateral en móvil (con fondo semitransparente).
        function setMobileDrawer(open) {
            document.querySelector('.sidebar').classList.toggle('open', open);
            document.getElementById('sidebar-overlay').classList.toggle('active', open);
            document.body.classList.toggle('nav-drawer-open', open);
            updateSidebarA11y();
        }

        function toggleSidebar() {
            setUserMenu(false);
            if (isMobileView()) {
                const sidebar = document.querySelector('.sidebar');
                setMobileDrawer(!sidebar.classList.contains('open'));
            } else {
                const container = document.querySelector('.app-container');
                container.classList.toggle('sidebar-collapsed');
                persistSidebarState();
                updateSidebarA11y();
            }
        }

        function setUserMenu(open) {
            const wrap = document.getElementById('user-menu-wrap');
            if (!wrap) {
                return;
            }
            wrap.classList.toggle('open', open);
            const trigger = wrap.querySelector('.sidebar-user');
            if (trigger) {
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }

        function toggleUserMenu() {
            const wrap = document.getElementById('user-menu-wrap');
            if (!wrap) {
                return;
            }
            setUserMenu(!wrap.classList.contains('open'));
        }

        // Cerrar el submenú de usuario al hacer clic fuera de él.
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('user-menu-wrap');
            if (wrap && wrap.classList.contains('open') && !wrap.contains(e.target)) {
                setUserMenu(false);
            }
        });

        function toggleTheme() {
            const root = document.documentElement;
            const theme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', theme);
            localStorage.setItem('actua_theme', theme);
        }

        // Limpia en tiempo real los campos que solo admiten letras y espacios.
        function soloLetras(input) {
            input.value = input.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '');
        }

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const button = input.nextElementSibling;
            const eyeOpen = button.querySelector('.eye-open');
            const eyeClosed = button.querySelector('.eye-closed');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                input.type = 'password';
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
        }

        // Cerrar el panel móvil al elegir una opción de navegación.
        document.querySelectorAll('.nav-menu a').forEach(function (link) {
            link.addEventListener('click', function () {
                setUserMenu(false);
                if (isMobileView()) {
                    setMobileDrawer(false);
                }
            });
        });

        // Teclado: Escape cierra el panel y el submenú; Enter/Espacio abren las categorías.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setUserMenu(false);
                if (isMobileView()) {
                    setMobileDrawer(false);
                }
            }

            const target = event.target;
            if (target && target.classList && target.classList.contains('nav-category-header')
                && (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar')) {
                event.preventDefault();
                target.click();
            }
        });

        // Mantener coherente el estado al cambiar de tamaño de pantalla.
        window.addEventListener('resize', function () {
            const container = document.querySelector('.app-container');
            if (isMobileView()) {
                setMobileDrawer(false);
                container.classList.remove('sidebar-collapsed');
            } else {
                try {
                    container.classList.toggle('sidebar-collapsed', localStorage.getItem(SIDEBAR_KEY) === 'collapsed');
                } catch (e) { /* almacenamiento no disponible */ }
            }
            updateSidebarA11y();
        });

        // Estado inicial de accesibilidad.
        updateSidebarA11y();
    </script>

    {{-- Scripts específicos de cada vista (p. ej. acciones de reportes) --}}
    @stack('scripts')
</body>
</html>
<?php /**PATH C:\laragon\www\proyecto\resources\views/layouts/app.blade.php ENDPATH**/ ?>
