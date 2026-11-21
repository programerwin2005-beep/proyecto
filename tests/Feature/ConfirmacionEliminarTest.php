<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Toda acción de eliminación debe pedir confirmación antes de enviarse.
 */
class ConfirmacionEliminarTest extends TestCase
{
    public function test_todos_los_botones_de_eliminar_piden_confirmacion(): void
    {
        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        $sinConfirmacion = [];

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($archivo->getPathname()) as $numero => $linea) {
                $esEliminacion = preg_match("/route\('[^']*(destroy|clear)[^']*'/", $linea);

                if ($esEliminacion && ! str_contains($linea, 'confirm(')) {
                    $sinConfirmacion[] = $archivo->getFilename().':'.($numero + 1);
                }
            }
        }

        $this->assertSame([], $sinConfirmacion, 'Acciones de eliminación sin confirmación: '.implode(', ', $sinConfirmacion));
    }

    public function test_los_mensajes_de_eliminacion_son_exitosos_y_simples(): void
    {
        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'))
        );

        $incorrectos = [];

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.php')) {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());

            if (str_contains($contenido, 'eliminado lógicamente')
                || str_contains($contenido, 'eliminada lógicamente')
                || str_contains($contenido, 'eliminados con éxito')
                || str_contains($contenido, 'eliminadas con éxito')) {
                $incorrectos[] = $archivo->getFilename();
            }
        }

        $this->assertSame([], $incorrectos, 'Controladores con mensajes de eliminación no estandarizados: '.implode(', ', $incorrectos));
    }
}
