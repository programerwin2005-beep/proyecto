<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Presupuesto mensual por categoría financiera.
 *
 * Solo se modela la tabla y sus relaciones; las pantallas y KPIs de
 * presupuesto quedan como trabajo futuro.
 */
class Presupuesto extends Model
{
    use SoftDeletes;

    protected $table = 'presupuestos';

    protected $fillable = [
        'anio',
        'mes',
        'categoria_id',
        'monto_planificado',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'monto_planificado' => 'decimal:2',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFinanciera::class, 'categoria_id');
    }
}
