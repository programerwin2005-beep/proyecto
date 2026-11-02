<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum DiaSemana: string
{
    use Etiquetable;

    case Lunes = 'Lunes';
    case Martes = 'Martes';
    case Miercoles = 'Miércoles';
    case Jueves = 'Jueves';
    case Viernes = 'Viernes';
    case Sabado = 'Sábado';
    case Domingo = 'Domingo';
}
