<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida campos de texto que solo deben contener letras y espacios.
 *
 * Se usa en nombres de personas, cargos, ministerios, cursos, actividades,
 * cultos, roles y campos equivalentes. No permite números ni signos.
 */
class SoloLetras implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El campo :attribute solo puede contener letras y espacios.');

            return;
        }

        $texto = trim($value);

        // \p{L} incluye letras con tilde, ñ, ü y alfabetos no latinos.
        if ($texto === '' || preg_match('/^[\p{L} ]+$/u', $texto) !== 1) {
            $fail('El campo :attribute solo puede contener letras y espacios, sin números ni signos.');
        }
    }
}
