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
            ->withPivot(['estado', 'observacion'])
            ->withTimestamps();
    }

    /**
     * Limita las sesiones a las de los cursos asignados al docente.
     * Se consideran tanto la asignación directa de la sesión como la
     * asignación del curso en la tabla docente_curso.
     */
    public function scopeParaDocente($query, int $docenteId)
    {
        return $query->where(function ($sub) use ($docenteId) {
            $sub->where('id_docente', $docenteId)
                ->orWhereHas('curso.docentes', fn ($q) => $q->where('docentes.id', $docenteId));
        });
    }
}
