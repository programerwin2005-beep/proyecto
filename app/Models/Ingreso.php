<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\MetodoPago;
use App\Enums\TipoIngreso;
use App\Models\Concerns\TieneRegistroUnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Ingreso extends Model
{
    use SoftDeletes, TieneRegistroUnico;

    /**
     * Valores resueltos: si el ingreso viene de un pago de curso, el monto,
     * la fecha y el método se leen del pago (Opción A, sin duplicar datos).
     */
    public const SQL_MONTO = 'COALESCE(ingresos.monto_total, pago_cursos.monto)';

    public const SQL_FECHA = 'COALESCE(ingresos.fecha, pago_cursos.fecha_pago)';

    public const SQL_METODO = 'COALESCE(ingresos.metodo_pago, pago_cursos.metodo_pago)';

    protected $table = 'ingresos';

    protected $fillable = [
        'id_actividad',
        'id_culto',
        'pago_curso_id',
        'categoria_id',
        'monto_total',
        'fecha',
        'tipo',
        'metodo_pago',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => EnumTolerante::class.':'.TipoIngreso::class,
            'metodo_pago' => EnumTolerante::class.':'.MetodoPago::class,
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFinanciera::class, 'categoria_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class, 'id_culto');
    }

    public function pagoCurso(): BelongsTo
    {
        return $this->belongsTo(PagoCurso::class, 'pago_curso_id');
    }

    /**
     * Une el pago de curso para poder resolver los importes con COALESCE.
     * El filtro por `deleted_at` va dentro del JOIN: si el pago fue eliminado,
     * el ingreso sigue existiendo y conserva su propio monto.
     */
    public function scopeConPago(Builder $query): Builder
    {
        return $query
            ->leftJoin('pago_cursos', function ($join) {
                $join->on('pago_cursos.id', '=', 'ingresos.pago_curso_id')
                    ->whereNull('pago_cursos.deleted_at');
            })
            ->select('ingresos.*');
    }

    /**
     * Total de ingresos, opcionalmente entre dos fechas.
     */
    public static function total(?string $desde = null, ?string $hasta = null): float
    {
        $query = static::query()
            ->leftJoin('pago_cursos', function ($join) {
                $join->on('pago_cursos.id', '=', 'ingresos.pago_curso_id')
                    ->whereNull('pago_cursos.deleted_at');
            });

        if ($desde !== null && $hasta !== null) {
            $query->whereBetween(DB::raw(self::SQL_FECHA), [$desde, $hasta]);
        }

        return (float) $query->sum(DB::raw(self::SQL_MONTO));
    }

    protected function montoTotal(): Attribute
    {
        return Attribute::get(fn ($value) => $value ?? $this->pagoCurso?->monto);
    }

    protected function fecha(): Attribute
    {
        return Attribute::get(fn ($value) => $value ?? $this->pagoCurso?->fecha_pago);
    }

    /**
     * El método de pago puede venir del propio ingreso o del pago de curso
     * asociado. Siempre se expone como enum, tolerando valores heredados.
     */
    protected function metodoPago(): Attribute
    {
        return Attribute::get(function ($value) {
            $metodo = $value ?? $this->pagoCurso?->metodo_pago;

            if ($metodo instanceof MetodoPago) {
                return $metodo;
            }

            return $metodo === null || $metodo === ''
                ? null
                : MetodoPago::tryFrom((string) $metodo);
        });
    }
}
