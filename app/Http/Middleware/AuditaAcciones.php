<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Registra acciones de creación, edición y eliminación en `audit_logs`.
 *
 * Solo guarda metadatos de la petición (módulo, acción, ruta, estado e IP).
 * Nunca almacena contraseñas, tokens ni el cuerpo de la solicitud.
 */
class AuditaAcciones
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->debeAuditar($request)) {
            return $response;
        }

        try {
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'modulo' => $this->modulo($request),
                'accion' => $this->accion($request),
                'entidad_type' => $request->route()?->getName(),
                'entidad_id' => $this->entidadId($request),
                'metadata' => json_encode([
                    'ruta' => $request->route()?->getName(),
                    'metodo' => $request->method(),
                    'estado' => $response->getStatusCode(),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe romper la operación del usuario.
            Log::warning('[auditoria] No se pudo registrar la acción: '.$e->getMessage());
        }

        return $response;
    }

    private function debeAuditar(Request $request): bool
    {
        if ($request->user() === null) {
            return false;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        $nombre = (string) $request->route()?->getName();

        foreach (['login', 'logout', 'password', 'verification', 'register'] as $excluido) {
            if (str_contains($nombre, $excluido)) {
                return false;
            }
        }

        // `asistencia.marcar-todos` y similares son acciones válidas: solo se
        // excluyen las rutas de autenticación y las que no tengan nombre.
        return $nombre !== '';
    }

    private function modulo(Request $request): string
    {
        $nombre = (string) $request->route()?->getName();

        if ($nombre === '') {
            return 'sistema';
        }

        return explode('.', $nombre)[0] ?: 'sistema';
    }

    private function accion(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'crear',
            'PUT', 'PATCH' => 'editar',
            'DELETE' => 'eliminar',
            default => 'otra',
        };
    }

    private function entidadId(Request $request): ?int
    {
        foreach ($request->route()?->parameters() ?? [] as $parametro) {
            if (is_numeric($parametro)) {
                return (int) $parametro;
            }

            if (is_object($parametro) && method_exists($parametro, 'getKey')) {
                return (int) $parametro->getKey();
            }
        }

        return null;
    }
}
