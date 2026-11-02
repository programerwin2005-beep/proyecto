<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona tablas pivote con `deleted_at` sin borrar filas físicamente.
 *
 * El UNIQUE de los pivotes incluye las filas eliminadas, por lo que volver a
 * insertar la misma relación falla. Estas operaciones restauran la fila
 * existente en lugar de crear otra, y marcan `deleted_at` al desvincular.
 */
final class VinculoPivote
{
    /**
     * Vincula uno o varios registros restaurando el pivote si estaba eliminado.
     *
     * @param  int|string|array<int, int|string>  $ids
     * @param  array<string, mixed>  $datos  Datos para el alta o la restauración.
     * @param  array<int, string>  $conservar  Columnas que no se sobrescriben si
     *                                         la fila existente ya tiene valor
     *                                         (por ejemplo `fecha_inicio`).
     */
    public static function adjuntar(
        BelongsToMany $relacion,
        int|string|array $ids,
        array $datos = [],
        array $conservar = []
    ): void {
        foreach ((array) $ids as $id) {
            $consulta = self::consulta($relacion)
                ->where($relacion->getRelatedPivotKeyName(), $id);

            if ($consulta->exists()) {
                $actualizacion = array_merge($datos, [
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

                if ($conservar !== []) {
                    $actuales = (clone $consulta)->first($conservar);

                    foreach ($conservar as $columna) {
                        if (($actuales->{$columna} ?? null) !== null && $actuales->{$columna} !== '') {
                            unset($actualizacion[$columna]);
                        }
                    }
                }

                $consulta->update($actualizacion);

                continue;
            }

            $relacion->attach([$id => $datos]);
        }
    }

    /**
     * Marca como eliminados los vínculos indicados (o todos si $ids es null).
     *
     * @param  int|string|array<int, int|string>|null  $ids
     */
    public static function desvincular(BelongsToMany $relacion, int|string|array|null $ids = null): void
    {
        $consulta = self::consulta($relacion)->whereNull('deleted_at');

        if ($ids !== null) {
            $consulta->whereIn($relacion->getRelatedPivotKeyName(), (array) $ids);
        }

        $consulta->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Deja exactamente los vínculos indicados, restaurando los que ya existían.
     *
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $datos  Datos para los vínculos nuevos o restaurados.
     * @param  array<int, string>  $conservar  Columnas que se mantienen al restaurar.
     */
    public static function sincronizar(
        BelongsToMany $relacion,
        array $ids,
        array $datos = [],
        array $conservar = []
    ): void {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $actuales = self::consulta($relacion)
            ->whereNull('deleted_at')
            ->pluck($relacion->getRelatedPivotKeyName())
            ->map(fn ($id) => (int) $id)
            ->all();

        $quitar = array_values(array_diff($actuales, $ids));
        $agregar = array_values(array_diff($ids, $actuales));

        if ($quitar !== []) {
            self::desvincular($relacion, $quitar);
        }

        if ($agregar !== []) {
            self::adjuntar($relacion, $agregar, $datos, $conservar);
        }
    }

    /**
     * Consulta base sobre la tabla pivote para el registro padre actual.
     */
    private static function consulta(BelongsToMany $relacion): Builder
    {
        $padre = $relacion->getParent();
        $clavePadre = $padre->getAttribute($relacion->getParentKeyName());

        return DB::table($relacion->getTable())
            ->where($relacion->getForeignPivotKeyName(), $clavePadre);
    }
}
