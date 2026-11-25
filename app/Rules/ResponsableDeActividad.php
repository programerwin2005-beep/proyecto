<?php

namespace App\Rules;

use App\Models\Actividad;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El responsable de una tarea de seguimiento debe ser el líder o un miembro
 * del ministerio que organiza la actividad. No se crea una relación nueva:
 * se valida contra el ministerio que ya tiene la actividad.
 */
class ResponsableDeActividad implements ValidationRule
{
    public function __construct(
        private readonly ?int $actividadId = null,
        private readonly ?string $responsableActual = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nombre = mb_strtolower(trim((string) $value));

        // Un registro histórico puede conservar su responsable aunque ya no
        // pertenezca al ministerio, siempre que no se cambie ese valor.
        if ($this->responsableActual !== null
            && $nombre === mb_strtolower(trim($this->responsableActual))) {
            return;
        }

        $actividad = $this->actividadId
            ? Actividad::find($this->actividadId)
            : null;

        if (! $actividad) {
            return;
        }

        $permitidos = $actividad->nombresResponsables()
            ->map(fn (string $nombre) => mb_strtolower($nombre));

        if (! $permitidos->contains($nombre)) {
            $fail('El responsable debe ser un miembro o el líder del ministerio que organiza la actividad.');
        }
    }
}
