<?php

use App\Http\Middleware\AuditaAcciones;
use App\Http\Middleware\CheckPermiso;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'prevent-back-history' => PreventBackHistory::class,
            'permiso' => CheckPermiso::class,
        ]);

        // Global: se aplica a TODAS las respuestas, incluidas 404 y errores
        // (las cabeceras de rutas web no cubren respuestas sin ruta coincidente).
        $middleware->prepend(SecureHeaders::class);

        $middleware->web(append: [
            CheckPermiso::class,
            AuditaAcciones::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
