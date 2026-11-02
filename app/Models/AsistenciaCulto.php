<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\EstadoAsistencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Asistencia individual a un culto.
 *
 * Solo se modela la tabla y sus relaciones; las pantallas y KPIs de asistencia
 * a cultos quedan como trabajo futuro.
 */
class AsistenciaCulto extends Model
{
    use SoftDeletes;

    protected $table = 'asistencias_culto';

    protected $fillable = [
        'miembro_id',
        'culto_id',
        'fecha',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EnumTolerante::class.':'.EstadoAsistencia::class,
        ];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'miembro_id');
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class, 'culto_id');
    }
}
