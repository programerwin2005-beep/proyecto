<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Miembro extends Model
{
    use SoftDeletes;

    protected $table = 'miembros';

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'direccion',
        'f_nacimiento',
        'sexo',
        'fecha_ingreso',
        'estado',
    ];

    public function docente(): HasOne
    {
        return $this->hasOne(Docente::class, 'id_miembro');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'id_miembro');
    }

    public function ministerios(): BelongsToMany
    {
        return $this->belongsToMany(Ministerio::class, 'miembro_ministerio')
            ->withPivot(['fecha_inicio', 'fecha_fin'])
            ->withTimestamps();
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'inscripciones')
            ->withPivot(['fecha_inscripcion', 'estado'])
            ->withTimestamps();
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function sesiones(): BelongsToMany
    {
        return $this->belongsToMany(Sesion::class, 'asistencias_sesion')
            ->withPivot(['estado', 'observacion'])
            ->withTimestamps();
    }
}
