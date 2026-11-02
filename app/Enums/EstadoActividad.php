<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum EstadoActividad: string
{
    use Etiquetable;

    case Programado = 'Programado';
    case EnProgreso = 'En Progreso';
    case Completada = 'Completada';
    case Cancelada = 'Cancelada';
}
