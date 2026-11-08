<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sesion extends Model
{
    use SoftDeletes;

    protected $table = 'sesiones';

    protected $fillable = [
        'id_curso',
        'id_docente',
        'fecha',
        'hora',
        'tema',
        'observacion',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'id_curso');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'id_docente');
    }

    public function miembrosAsistencia(): BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'asistencias_sesion')
            ->withPivot(['estado', 'observacion', 'deleted_at'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Limita las sesiones asignadas directamente al docente.
     */
    public function scopeParaDocente($query, int $docenteId)
    {
        return $query->where('id_docente', $docenteId);
    }
}
