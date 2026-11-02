<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\EstadoAsistencia;
use App\Models\Concerns\TieneRegistroUnico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asistencia extends Model
{
    use SoftDeletes, TieneRegistroUnico;

    protected $table = 'asistencias_sesion';

    protected $fillable = [
        'miembro_id',
        'user_id',
        'sesion_id',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EnumTolerante::class.':'.EstadoAsistencia::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'miembro_id');
    }

    public function curso()
    {
        return $this->hasOneThrough(
            Curso::class,
            Sesion::class,
            'id',
            'id',
            'sesion_id',
            'id_curso'
        );
    }

    public function scopePorCurso($query, int $cursoId)
    {
        return $query->whereHas('sesion', fn ($q) => $q->where('id_curso', $cursoId));
    }

    public function scopePorMiembro($query, int $miembroId)
    {
        return $query->where('miembro_id', $miembroId);
    }

    public function getCursoNombreAttribute(): ?string
    {
        return $this->sesion?->curso?->nombre;
    }
}
