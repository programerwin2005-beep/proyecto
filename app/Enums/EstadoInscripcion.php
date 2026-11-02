<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum EstadoInscripcion: string
{
    use Etiquetable;

    case Activo = 'Activo';
    case Retirado = 'Retirado';
    case Completado = 'Completado';
}
