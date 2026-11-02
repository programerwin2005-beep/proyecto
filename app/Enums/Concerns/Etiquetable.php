<?php

namespace App\Enums\Concerns;

/**
 * Utilidades comunes para los enums de dominio.
 */
trait Etiquetable
{
    /**
     * Etiqueta legible del valor (por defecto, el propio valor).
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Arreglo valor => etiqueta para poblar selects.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->label();
        }

        return $opciones;
    }

    /**
     * Lista de valores válidos.
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
