<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\DiaSemana;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Culto extends Model
{
    use SoftDeletes;

    protected $table = 'cultos';

    protected $fillable = [
        'nombre',
        'dia_semana',
        'hora',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => EnumTolerante::class.':'.DiaSemana::class,
        ];
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'id_culto');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(AsistenciaCulto::class, 'culto_id');
    }
}
