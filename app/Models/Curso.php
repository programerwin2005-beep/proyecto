<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Un curso se considera finalizado cuando su fecha de fin ya pasó.
     * Sin fecha de fin se mantiene abierto.
     */
    public function haFinalizado(): bool
    {
        return $this->f_fin !== null && $this->f_fin < now()->toDateString();
    }

    /**
     * Cursos que todavía no han terminado (incluye los que no tienen fecha de fin).
     */
    public function scopeNoFinalizados(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where(function (Builder $sub) use ($hoy) {
            $sub->whereNull('f_fin')->orWhereDate('f_fin', '>=', $hoy);
        });
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
     * Cursos que dicta el docente: aquellos con al menos una sesión
     * asignada directamente a él.
     */
    public function scopeParaDocente($query, int $docenteId)
    {
        return $query->whereHas('sesiones', fn ($q) => $q->where('id_docente', $docenteId));
    }
}
