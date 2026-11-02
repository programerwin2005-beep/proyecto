<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast a enum que no falla ante valores heredados desconocidos.
 *
 * El valor original permanece en la base de datos; el modelo expone `null`
 * hasta que el dato se normalice. Al guardar se acepta el enum o un string.
 *
 * @implements CastsAttributes<BackedEnum|null, BackedEnum|string|null>
 */
class EnumTolerante implements CastsAttributes
{
    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function __construct(private string $enum)
    {
        if (! enum_exists($this->enum)) {
            throw new InvalidArgumentException("La clase {$this->enum} no es un enum válido.");
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->enum::tryFrom($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
