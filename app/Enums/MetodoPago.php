<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum MetodoPago: string
{
    use Etiquetable;

    case Efectivo = 'Efectivo';
    case Transferencia = 'Transferencia';
    case Tarjeta = 'Tarjeta';
}
