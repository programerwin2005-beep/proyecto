<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum EstadoMiembro: string
{
    use Etiquetable;

    case Activo = 'Activo';
    case Inactivo = 'Inactivo';
}
