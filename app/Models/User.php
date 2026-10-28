<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Caché en memoria de los permisos resueltos del usuario.
     *
     * @var array<string, bool>|null
     */
    protected ?array $permisosResueltos = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function docente(): HasOne
    {
        return $this->hasOne(Docente::class, 'user_id');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('nombre', $roleName)->exists();
    }

    public function esDocente(): bool
    {
        return $this->hasRole('Docente');
    }

    public function docenteActivo(): bool
    {
        return (bool) $this->docente?->activo;
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole('Administrador');
    }

    /**
     * Indica si el usuario (a través de alguno de sus roles) puede realizar
     * la acción indicada sobre el módulo. El rol Administrador siempre puede.
     */
    public function puede(string $modulo, string $accion): bool
    {
        $permisos = $this->resolverPermisos();

        if (! empty($permisos['*'])) {
            return true;
        }

        return ! empty($permisos["{$modulo}.{$accion}"]);
    }

    /**
     * Limpia la caché de permisos (útil si los roles cambian en la petición).
     */
    public function limpiarPermisos(): void
    {
        $this->permisosResueltos = null;
    }

    /**
     * @return array<string, bool>
     */
    private function resolverPermisos(): array
    {
        if ($this->permisosResueltos !== null) {
            return $this->permisosResueltos;
        }

        $mapa = [];

        foreach ($this->roles()->with('permisos')->get() as $role) {
            if ($role->nombre === 'Administrador') {
                return $this->permisosResueltos = ['*' => true];
            }

            foreach ($role->permisos as $permiso) {
                foreach (['ver', 'crear', 'editar', 'eliminar', 'buscar'] as $accion) {
                    if ($permiso->{$accion}) {
                        $mapa["{$permiso->modulo}.{$accion}"] = true;
                    }
                }
            }
        }

        return $this->permisosResueltos = $mapa;
    }
}
