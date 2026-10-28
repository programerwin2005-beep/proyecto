<?php

/*
|--------------------------------------------------------------------------
| Configuración de Permisos
|--------------------------------------------------------------------------
|
| Aquí se definen los módulos del sistema agrupados por categoría y las
| acciones (ver, crear, editar, eliminar, buscar) que aplican a cada uno.
| También se construye el mapa ruta -> [módulo, acción] que usa el
| middleware CheckPermiso para autorizar cada petición.
|
*/

$acciones = [
    'ver' => 'Ver',
    'crear' => 'Crear',
    'editar' => 'Editar',
    'eliminar' => 'Eliminar',
    'buscar' => 'Buscar',
];

$categorias = [
    'Acceso y Seguridad' => [
        'usuarios' => ['label' => 'Usuarios', 'acciones' => ['ver', 'crear', 'editar', 'eliminar']],
        'roles' => ['label' => 'Roles', 'acciones' => ['ver', 'crear', 'editar', 'eliminar']],
        'permisos' => ['label' => 'Permisos', 'acciones' => ['ver', 'editar']],
    ],
    'Parametrización' => [
        'miembros' => ['label' => 'Miembros', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'cargos' => ['label' => 'Cargos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'lideres' => ['label' => 'Líderes de Iglesia', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'ministerios' => ['label' => 'Ministerios', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'actividades' => ['label' => 'Actividades', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'seguimiento' => ['label' => 'Seguimiento', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'cursos' => ['label' => 'Cursos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'docentes' => ['label' => 'Docentes', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'contratos' => ['label' => 'Contratos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
    ],
    'Transaccional' => [
        'inscripciones' => ['label' => 'Inscripciones', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'sesiones' => ['label' => 'Sesiones', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'asistencia' => ['label' => 'Asistencia', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'cultos' => ['label' => 'Cultos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'ingresos' => ['label' => 'Ingresos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
        'egresos' => ['label' => 'Egresos', 'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'buscar']],
    ],
    'Reportes' => [
        'reportes.miembros' => ['label' => 'Reporte de Miembros', 'acciones' => ['ver']],
        'reportes.asistencia' => ['label' => 'Reporte de Asistencia', 'acciones' => ['ver']],
        'reportes.financiero' => ['label' => 'Reporte Financiero', 'acciones' => ['ver']],
    ],
];

// Mapeo genérico de rutas resource: sufijo -> acción
$crud = [
    'index' => 'ver',
    'show' => 'ver',
    'create' => 'crear',
    'store' => 'crear',
    'edit' => 'editar',
    'update' => 'editar',
    'destroy' => 'eliminar',
];

// módulo => prefijo de ruta
$recursos = [
    'usuarios' => 'users',
    'roles' => 'roles',
    'miembros' => 'miembros',
    'cargos' => 'cargos',
    'lideres' => 'lideres',
    'ministerios' => 'ministerios',
    'actividades' => 'actividades',
    'seguimiento' => 'seguimiento',
    'cursos' => 'cursos',
    'docentes' => 'docentes',
    'contratos' => 'contratos',
    'inscripciones' => 'inscripciones',
    'sesiones' => 'sesiones',
    'cultos' => 'cultos',
    'ingresos' => 'ingresos',
    'egresos' => 'egresos',
];

$rutas = [];
foreach ($recursos as $modulo => $prefijo) {
    foreach ($crud as $sufijo => $accion) {
        $rutas["{$prefijo}.{$sufijo}"] = [$modulo, $accion];
    }
}

$rutas += [
    // Roles: asignación de roles a usuarios
    'roles.user' => ['roles', 'ver'],
    'roles.user.edit' => ['roles', 'editar'],
    'roles.user.save' => ['roles', 'editar'],
    'roles.user.clear' => ['roles', 'editar'],

    // Inscripciones
    'inscripciones.gestion' => ['inscripciones', 'ver'],

    // Acceso del docente (crear su usuario)
    'docentes.acceso' => ['docentes', 'editar'],
    'docentes.acceso.store' => ['docentes', 'editar'],

    // Pagos (parte del módulo Inscripciones)
    'pagos.create' => ['inscripciones', 'crear'],
    'pagos.store' => ['inscripciones', 'crear'],
    'pagos.destroy' => ['inscripciones', 'eliminar'],

    // Sesiones
    'sesiones.generar' => ['sesiones', 'crear'],

    // Asistencia
    'asistencia.index' => ['asistencia', 'ver'],
    'asistencia.registrar' => ['asistencia', 'ver'],
    'asistencia.store' => ['asistencia', 'crear'],
    'asistencia.marcarTodos' => ['asistencia', 'crear'],
    'asistencia.historial' => ['asistencia', 'ver'],

    // Contratos
    'contratos.renovar' => ['contratos', 'editar'],

    // Permisos
    'permisos.index' => ['permisos', 'ver'],
    'permisos.edit' => ['permisos', 'ver'],
    'permisos.update' => ['permisos', 'editar'],

    // Reportes
    'reportes.miembros' => ['reportes.miembros', 'ver'],
    'reportes.asistencia' => ['reportes.asistencia', 'ver'],
    'reportes.financiero' => ['reportes.financiero', 'ver'],
];

return [
    'acciones' => $acciones,
    'categorias' => $categorias,
    'rutas' => $rutas,

    // Módulos cuyo listado tiene buscador (?q=). Requieren el permiso "buscar".
    'modulos_con_busqueda' => ['miembros', 'inscripciones', 'asistencia', 'contratos'],
];
