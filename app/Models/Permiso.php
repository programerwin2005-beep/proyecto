<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'role_id',
        'modulo',
        'ver',
        'crear',
        'editar',
        'eliminar',
        'buscar',
        'asignar_roles',
        'gestionar_acceso',
        'generar_sesiones',
        'marcar_todos',
        'ver_historial',
        'completar',
        'renovar',
        'pagar_contrato',
        'ingreso_actividad',
    ];

    protected function casts(): array
    {
        return [
            'ver' => 'boolean',
            'crear' => 'boolean',
            'editar' => 'boolean',
            'eliminar' => 'boolean',
            'buscar' => 'boolean',
            'asignar_roles' => 'boolean',
            'gestionar_acceso' => 'boolean',
            'generar_sesiones' => 'boolean',
            'marcar_todos' => 'boolean',
            'ver_historial' => 'boolean',
            'completar' => 'boolean',
            'renovar' => 'boolean',
            'pagar_contrato' => 'boolean',
            'ingreso_actividad' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
