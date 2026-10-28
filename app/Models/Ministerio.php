<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ministerio extends Model
{
    use SoftDeletes;

    protected $table = 'ministerios';

    protected $fillable = [
        'nombre',
        'descripcion',
        'id_lider_iglesia',
    ];

    /**
     * Líder de iglesia responsable del ministerio.
     */
    public function lider(): BelongsTo
    {
        return $this->belongsTo(LiderIglesia::class, 'id_lider_iglesia');
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'miembro_ministerio')
            ->withPivot(['fecha_inicio', 'fecha_fin'])
            ->withTimestamps();
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'id_ministerio');
    }
}
