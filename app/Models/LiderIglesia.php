<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiderIglesia extends Model
{
    use SoftDeletes;

    protected $table = 'lideres_iglesia';

    protected $fillable = [
        'id_cargo',
        'nombre',
        'telefono',
        'fecha_inicio',
        'activo',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'id_cargo');
    }

    public function ministerios(): HasMany
    {
        return $this->hasMany(Ministerio::class, 'id_lider_iglesia');
    }
}
