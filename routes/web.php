<?php

use App\Http\Controllers\ActividadController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\CultoController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EgresoController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\LiderIglesiaController;
use App\Http\Controllers\MiembroController;
use App\Http\Controllers\MinisterioController;
use App\Http\Controllers\PagoCursoController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SeguimientoActividadController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Sistema interno: la raíz siempre lleva al login o al dashboard.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Rutas de Autenticación (Invitados) — el envío del formulario va limitado
// por intentos para mitigar fuerza bruta (OWASP A07).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Rutas Protegidas por Autenticación
Route::middleware(['auth', 'prevent-back-history'])->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reportes (accesible para cualquier usuario autenticado)
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('reportes/miembros', [ReporteController::class, 'miembros'])->name('reportes.miembros');
    Route::get('reportes/asistencia', [ReporteController::class, 'asistencia'])->name('reportes.asistencia');
    Route::get('reportes/financiero', [ReporteController::class, 'financiero'])->name('reportes.financiero');

    // Acceso y Seguridad (permisos por rol: usuarios, roles)
    Route::group([], function () {
        Route::resource('users', UserController::class);

        Route::get('roles/assign', [RoleController::class, 'userRoles'])->name('roles.user');
        Route::get('roles/assign/{user}/edit', [RoleController::class, 'userRolesEdit'])->name('roles.user.edit');
        Route::post('roles/assign', [RoleController::class, 'assignRole'])->name('roles.user.save');
        Route::post('roles/assign/{id}/clear', [RoleController::class, 'clearUserRoles'])->name('roles.user.clear');
        Route::resource('roles', RoleController::class);
    });

    // Administrar Permisos (solo Administrador)
    Route::middleware('role:Administrador')->group(function () {
        Route::get('permisos', [PermisoController::class, 'index'])->name('permisos.index');
        Route::get('permisos/{role}', [PermisoController::class, 'edit'])->name('permisos.edit');
        Route::put('permisos/{role}', [PermisoController::class, 'update'])->name('permisos.update');
    });

    // Parametrización y módulos operativos (permisos por rol)
    Route::group([], function () {
        // 1. Cargos
        Route::resource('cargos', CargoController::class);

        // 2. Líderes de Iglesia
        Route::resource('lideres', LiderIglesiaController::class);

        // 3. Miembros
        Route::resource('miembros', MiembroController::class);

        // 4. Ministerios
        Route::resource('ministerios', MinisterioController::class);

        // 5. Actividades
        Route::resource('actividades', ActividadController::class);

        // 6. Seguimiento de Actividades
        Route::resource('seguimiento', SeguimientoActividadController::class);

        // 7. Cursos
        Route::resource('cursos', CursoController::class);

        // 7.1 Inscripciones a Cursos (módulo aparte)
        Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
        Route::get('inscripciones/{curso}/gestionar', [InscripcionController::class, 'gestion'])->name('inscripciones.gestion');
        Route::get('inscripciones/{inscripcion}/edit', [InscripcionController::class, 'edit'])->name('inscripciones.edit');
        Route::put('inscripciones/{inscripcion}', [InscripcionController::class, 'update'])->name('inscripciones.update');
        Route::post('cursos/{curso}/inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
        Route::delete('inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');

        // 7.2 Pagos de Cursos (generan Ingreso automáticamente)
        Route::get('inscripciones/{inscripcion}/pagos/create', [PagoCursoController::class, 'create'])->name('pagos.create');
        Route::post('inscripciones/{inscripcion}/pagos', [PagoCursoController::class, 'store'])->name('pagos.store');
        Route::delete('pagos/{pago}', [PagoCursoController::class, 'destroy'])->name('pagos.destroy');

        // 8. Docentes
        Route::get('docentes/{docente}/acceso', [DocenteController::class, 'acceso'])->name('docentes.acceso');
        Route::post('docentes/{docente}/acceso', [DocenteController::class, 'guardarAcceso'])->name('docentes.acceso.store');
        Route::resource('docentes', DocenteController::class);

        // 9. Sesiones
        Route::post('cursos/{curso}/sesiones/generar', [SesionController::class, 'generar'])->name('sesiones.generar');
        Route::get('miembros/{miembroId}/asistencia', [AsistenciaController::class, 'historial'])->name('asistencia.historial');
        Route::resource('sesiones', SesionController::class);

        // 9b. Asistencia (módulo propio)
        Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
        Route::get('asistencia/registrar', [AsistenciaController::class, 'registrar'])->name('asistencia.registrar');
        Route::post('asistencia/registrar/{sesion}', [AsistenciaController::class, 'store'])->name('asistencia.store');
        Route::post('asistencia/registrar/{sesion}/marcar-todos', [AsistenciaController::class, 'marcarTodos'])->name('asistencia.marcarTodos');
    });

    // Finanzas (permisos por rol: cultos, ingresos, egresos, contratos)
    Route::group([], function () {
        // 10. Cultos
        Route::resource('cultos', CultoController::class);

        // 11. Ingresos
        Route::resource('ingresos', IngresoController::class);

        // 12. Egresos
        Route::resource('egresos', EgresoController::class);

        // 13. Contratos
        Route::get('contratos/{id}/renovar', [ContratoController::class, 'renovar'])->name('contratos.renovar');
        Route::resource('contratos', ContratoController::class);
    });
});
