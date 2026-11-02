<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de categorías para clasificar ingresos y egresos.
 *
 * El texto libre (`ingresos.tipo`, `egresos.tipo_egreso`) se conserva como
 * respaldo; `categoria_id` es la referencia normalizada.
 */
class CategoriaFinanciera extends Model
{
    use SoftDeletes;

    public const NATURALEZA_INGRESO = 'ingreso';

    public const NATURALEZA_EGRESO = 'egreso';

    protected $table = 'categorias_financieras';

    protected $fillable = [
        'nombre',
        'naturaleza',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Devuelve el id de la categoría activa que corresponde a un texto.
     */
    public static function idPara(string $nombre, string $naturaleza): ?int
    {
        return static::query()
            ->where('nombre', $nombre)
            ->where('naturaleza', $naturaleza)
            ->value('id');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'categoria_id');
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class, 'categoria_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'categoria_id');
    }
}
