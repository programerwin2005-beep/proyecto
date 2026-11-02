<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\DiaSemana;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Culto extends Model
{
    use SoftDeletes;

    protected $table = 'cultos';

    protected $fillable = [
        'id_lider_iglesia',
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

    public function liderIglesia(): BelongsTo
    {
        return $this->belongsTo(LiderIglesia::class, 'id_lider_iglesia');
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
