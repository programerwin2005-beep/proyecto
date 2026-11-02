<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum TipoIngreso: string
{
    use Etiquetable;

    case Diezmo = 'Diezmo';
    case Ofrenda = 'Ofrenda';
    case Donacion = 'Donación';
    case Inscripcion = 'Inscripción';
    case Actividad = 'Actividad';
    case Otros = 'Otros';
}
