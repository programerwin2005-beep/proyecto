<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\TipoEgreso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Egreso extends Model
{
    use SoftDeletes;

    protected $table = 'egresos';

    protected $fillable = [
        'id_contrato',
        'id_lider_iglesia',
        'categoria_id',
        'tipo_egreso',
        'monto',
        'fecha',
        'descripcion',
        'responsable',
        'comprobante',
    ];

    protected function casts(): array
    {
        return [
            'tipo_egreso' => EnumTolerante::class.':'.TipoEgreso::class,
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFinanciera::class, 'categoria_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'id_contrato');
    }

    public function lider(): BelongsTo
    {
        return $this->belongsTo(LiderIglesia::class, 'id_lider_iglesia');
    }

    public function liderIglesia()
    {
        return $this->hasOneThrough(
            LiderIglesia::class,
            Contrato::class,
            'id',
            'id',
            'id_contrato',
            'id_lider_iglesia'
        );
    }
}
