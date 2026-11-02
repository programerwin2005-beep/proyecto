<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum TipoEgreso: string
{
    use Etiquetable;

    case Mantenimiento = 'Mantenimiento';
    case ServiciosBasicos = 'Servicios Básicos';
    case Salarios = 'Salarios';
    case Honorarios = 'Honorarios';
    case AyudaComunitaria = 'Ayuda Comunitaria';
    case OtrosEgresos = 'Otros Egresos';
}
