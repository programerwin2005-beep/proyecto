<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Curso extends Model
{
    use SoftDeletes;

    protected $table = 'cursos';

    protected $fillable = [
        'nombre',
        'f_inicio',
        'f_fin',
        'cupo_max',
        'dias_semana',
        'hora_inicio',
        'hora_fin',
        'tiene_pago',
        'monto_inscripcion',
    ];

    protected function casts(): array
    {
        return [
            'dias_semana' => 'array',
        ];
    }

    public function getDiasNombresAttribute(): string
    {
        $mapa = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

        return collect($this->dias_semana ?? [])
            ->sort()
            ->map(fn ($d) => $mapa[$d] ?? '?')
            ->implode(' y ');
    }

    public function tieneHorario(): bool
    {
        return ! empty($this->dias_semana) && ! is_null($this->hora_inicio);
    }

    public function rangoHorario(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).' - '.substr((string) $this->hora_fin, 0, 5);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'docente_curso')->withTimestamps();
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'id_curso');
    }

    /**
     * Cursos del docente: los asignados en docente_curso o los que tienen
     * alguna sesión asignada directamente al docente.
     */
    public function scopeParaDocente($query, int $docenteId)
    {
        return $query->where(function ($sub) use ($docenteId) {
            $sub->whereHas('docentes', fn ($q) => $q->where('docentes.id', $docenteId))
                ->orWhereHas('sesiones', fn ($q) => $q->where('id_docente', $docenteId));
        });
    }
}
