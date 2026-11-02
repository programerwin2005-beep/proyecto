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
