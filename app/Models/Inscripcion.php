<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inscripcion extends Model
{
    use SoftDeletes;

    protected $table = 'inscripciones';

    protected $fillable = [
        'miembro_id',
        'curso_id',
        'fecha_inscripcion',
        'estado',
    ];

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCurso::class);
    }

    public function getMontoPagadoAttribute(): float
    {
        return (float) ($this->pagos_sum_monto ?? $this->pagos()->sum('monto'));
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->curso->monto_inscripcion - $this->monto_pagado);
    }

    public function getEstadoPagoAttribute(): string
    {
        if ($this->saldo_pendiente <= 0) {
            return 'Pagado';
        }

        return $this->monto_pagado > 0 ? 'Pago Parcial' : 'Pendiente';
    }

    public static function getMiembrosDisponibles(int $cursoId)
    {
        return Miembro::where('estado', 'Activo')
            ->whereDoesntHave('inscripciones', fn ($q) => $q->withTrashed()->where('curso_id', $cursoId))
            ->orderBy('nombre')
            ->get();
    }
}
