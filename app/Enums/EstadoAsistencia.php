<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum EstadoAsistencia: string
{
    use Etiquetable;

    case Presente = 'Presente';
    case Ausente = 'Ausente';
    case Tardanza = 'Tardanza';
    case Justificado = 'Justificado';
}
