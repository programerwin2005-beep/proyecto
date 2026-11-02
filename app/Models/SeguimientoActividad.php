<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\EstadoSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeguimientoActividad extends Model
{
    use SoftDeletes;

    protected $table = 'seguimiento_actividades';

    protected $fillable = [
        'id_actividad',
        'fecha_registro',
        'observacion',
        'responsable',
        'porcentaje_avance',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EnumTolerante::class.':'.EstadoSeguimiento::class,
        ];
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }
}
