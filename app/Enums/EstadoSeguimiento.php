<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum EstadoSeguimiento: string
{
    use Etiquetable;

    case EnProgreso = 'En Progreso';
    case Completado = 'Completado';
    case Detenido = 'Detenido';
}
