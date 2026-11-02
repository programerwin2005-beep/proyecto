<?php

namespace App\Enums;

use App\Enums\Concerns\Etiquetable;

enum TipoCompensacion: string
{
    use Etiquetable;

    case Salario = 'Salario';
    case Bono = 'Bono';
    case Beneficios = 'Beneficios';
    case Voluntario = 'Voluntario';
}
