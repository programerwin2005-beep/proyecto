<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'nivel_acceso',
        'guard_name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }

    /**
     * Indica si el rol tiene concedida la acción sobre el módulo dado.
     */
    public function tienePermiso(string $modulo, string $accion): bool
    {
        if (! array_key_exists($accion, config('permisos.acciones', []))) {
            return false;
        }

        $permiso = $this->relationLoaded('permisos')
            ? $this->permisos->firstWhere('modulo', $modulo)
            : $this->permisos()->where('modulo', $modulo)->first();

        return $permiso ? (bool) $permiso->{$accion} : false;
    }
}
