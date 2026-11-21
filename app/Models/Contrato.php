<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\TipoCompensacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrato extends Model
{
    use SoftDeletes;

    protected $table = 'contratos';

    const TIPO_SALARIO = 'Salario';

    const TIPO_BONO = 'Bono';

    const TIPO_BENEFICIOS = 'Beneficios';

    const TIPO_VOLUNTARIO = 'Voluntario';

    public static function tiposCompensacion(): array
    {
        return TipoCompensacion::opciones();
    }

    public function esVoluntario(): bool
    {
        return $this->tipo_compensacion === TipoCompensacion::Voluntario;
    }

    protected $fillable = [
        'id_miembro',
        'id_lider_iglesia',
        'tipo_compensacion',
        'salario',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tipo_compensacion' => EnumTolerante::class.':'.TipoCompensacion::class,
        ];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }

    public function lider(): BelongsTo
    {
        return $this->belongsTo(LiderIglesia::class, 'id_lider_iglesia');
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class, 'id_contrato');
    }

    /**
     * Periodos mensuales del contrato. Cada mes inicia el mismo día en que
     * empezó el contrato y termina el día anterior del mes siguiente.
     *
     * @return \Illuminate\Support\Collection<int, array{inicio: Carbon, fin: Carbon, monto: float, pagado: float, restante: float, completo: bool}>
     */
    public function periodosPago(?Carbon $hasta = null): \Illuminate\Support\Collection
    {
        $hoy = ($hasta ?: now())->copy()->startOfDay();
        $inicioContrato = Carbon::parse($this->fecha_inicio)->startOfDay();

        if ($inicioContrato->gt($hoy)) {
            return collect();
        }

        if ($this->fecha_fin) {
            $finContrato = Carbon::parse($this->fecha_fin)->startOfDay();

            if ($finContrato->lt($hoy)) {
                $hoy = $finContrato;
            }
        }

        $egresos = $this->relationLoaded('egresos')
            ? $this->egresos
            : $this->egresos()->get();

        $monto = (float) $this->salario;
        $periodos = collect();
        $numero = 0;

        while (true) {
            $inicio = $inicioContrato->copy()->addMonthsNoOverflow($numero);

            if ($inicio->gt($hoy)) {
                break;
            }

            $fin = $inicioContrato->copy()->addMonthsNoOverflow($numero + 1)->subDay();

            $pagado = (float) $egresos
                ->filter(fn (Egreso $egreso) => $this->egresoPerteneceAlPeriodo($egreso, $inicio, $fin))
                ->sum('monto');

            $periodos->push([
                'inicio' => $inicio->copy(),
                'fin' => $fin,
                'monto' => $monto,
                'pagado' => $pagado,
                'restante' => max(0.0, round($monto - $pagado, 2)),
                'completo' => $pagado + 0.005 >= $monto,
            ]);

            $numero++;
        }

        return $periodos;
    }

    /**
     * Periodo mensual que contiene la fecha indicada (por defecto hoy).
     *
     * @return array{inicio: Carbon, fin: Carbon, monto: float, pagado: float, restante: float, completo: bool}|null
     */
    public function periodoDelDia(?Carbon $dia = null): ?array
    {
        $dia = ($dia ?: now())->copy()->startOfDay();

        return $this->periodosPago($dia)->last();
    }

    /**
     * Periodo que empieza exactamente en la fecha indicada.
     *
     * @return array{inicio: Carbon, fin: Carbon, monto: float, pagado: float, restante: float, completo: bool}|null
     */
    public function periodoPorInicio(string $fecha): ?array
    {
        return $this->periodosPago()
            ->first(fn (array $periodo) => $periodo['inicio']->toDateString() === $fecha);
    }

    /**
     * El contrato ya cumplió su vigencia (fecha de fin anterior a hoy).
     */
    public function contratoCumplido(): bool
    {
        return $this->fecha_fin !== null
            && Carbon::parse($this->fecha_fin)->startOfDay()->lt(now()->startOfDay());
    }

    /**
     * Indica si queda al menos un mes del contrato sin completar su pago.
     */
    public function tieneMesesPendientes(): bool
    {
        return $this->periodosPago()->contains(fn (array $periodo) => ! $periodo['completo']);
    }

    private function egresoPerteneceAlPeriodo(Egreso $egreso, Carbon $inicio, Carbon $fin): bool
    {
        if ($egreso->deleted_at !== null) {
            return false;
        }

        if ($egreso->periodo_inicio) {
            return Carbon::parse($egreso->periodo_inicio)->isSameDay($inicio);
        }

        return Carbon::parse($egreso->fecha)->betweenIncluded($inicio, $fin);
    }

    public function getVigenciaAttribute(): string
    {
        $hoy = now()->startOfDay();
        $fin = $this->fecha_fin ? Carbon::parse($this->fecha_fin) : null;

        return match (true) {
            $this->trashed() => 'Eliminado',
            Carbon::parse($this->fecha_inicio)->gt($hoy) => 'Futuro',
            $fin === null => 'Vigente',
            $fin->lt($hoy) => 'Vencido',
            $fin->lte($hoy->copy()->addDays(30)) => 'Por vencer',
            default => 'Vigente',
        };
    }

    public function getVigenteAttribute(): bool
    {
        return in_array($this->vigencia, ['Vigente', 'Por vencer']);
    }

    public static function tieneSolapamiento(int $miembroId, string $fechaInicio, ?string $fechaFin, ?int $ignorarId = null): bool
    {
        $query = self::where('id_miembro', $miembroId)
            ->where('fecha_inicio', '<=', $fechaFin ?? '9999-12-31')
            ->where(function ($q) use ($fechaInicio) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fechaInicio);
            });

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->exists();
    }
}
