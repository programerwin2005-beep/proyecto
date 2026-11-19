<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\MetodoPago;
use App\Enums\TipoIngreso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Pago extends Model
{
    use SoftDeletes;

    protected $table = 'pagos';

    protected $fillable = [
        'inscripcion_id',
        'monto',
        'fecha_pago',
        'metodo_pago',
        'comprobante',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'metodo_pago' => EnumTolerante::class.':'.MetodoPago::class,
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function ingreso(): HasOne
    {
        return $this->hasOne(Ingreso::class, 'pago_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Registra un pago y crea su ingreso copiando monto, fecha y método.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(Inscripcion $inscripcion, array $datos): self
    {
        return DB::transaction(function () use ($inscripcion, $datos) {
            /** @var self $pago */
            $pago = $inscripcion->pagos()->create(
                $datos + ['registrado_por' => auth()->id()]
            );

            $pago->sincronizarIngreso();

            return $pago;
        });
    }

    /**
     * Copia los datos del pago al ingreso asociado (se crea o restaura).
     */
    public function sincronizarIngreso(): void
    {
        Ingreso::crearORestaurar(
            ['pago_id' => $this->id],
            [
                'monto_total' => $this->monto,
                'fecha' => $this->fecha_pago,
                'metodo_pago' => $this->getRawOriginal('metodo_pago'),
                'tipo' => TipoIngreso::Inscripcion,
                'categoria_id' => CategoriaFinanciera::idPara(
                    TipoIngreso::Inscripcion->value,
                    CategoriaFinanciera::NATURALEZA_INGRESO
                ),
            ]
        );
    }
}
