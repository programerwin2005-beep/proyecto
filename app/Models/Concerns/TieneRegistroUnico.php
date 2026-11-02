<?php

namespace App\Models\Concerns;

/**
 * Reutiliza un registro con clave UNIQUE que fue eliminado lógicamente.
 *
 * Los índices UNIQUE de la base incluyen las filas con `deleted_at`, por lo que
 * un INSERT nuevo falla mientras el registro anterior siga existiendo. En lugar
 * de usar índices parciales (no portables entre MySQL y SQLite), se busca el
 * registro con `withTrashed()` y se restaura.
 */
trait TieneRegistroUnico
{
    /**
     * Devuelve el registro que ocupa la clave única, incluso si está eliminado.
     *
     * @param  array<string, mixed>  $claves
     */
    public static function buscarPorClaveUnica(array $claves): ?static
    {
        return static::withTrashed()->where($claves)->first();
    }

    /**
     * Crea el registro o restaura el eliminado que ocupa la misma clave única.
     *
     * @param  array<string, mixed>  $claves
     * @param  array<string, mixed>  $valores
     */
    public static function crearORestaurar(array $claves, array $valores = []): static
    {
        $registro = static::buscarPorClaveUnica($claves);

        if ($registro === null) {
            return static::create($claves + $valores);
        }

        if (method_exists($registro, 'trashed') && $registro->trashed()) {
            $registro->restore();
        }

        if ($valores !== []) {
            $registro->fill($valores)->save();
        }

        return $registro;
    }
}
