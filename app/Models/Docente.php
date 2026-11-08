<?php

namespace App\Models;

use App\Models\Concerns\TieneRegistroUnico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Docente extends Model
{
    use SoftDeletes, TieneRegistroUnico;

    protected $table = 'docentes';

    protected $fillable = [
        'id_miembro',
        'user_id',
        'especialidad',
        'titulo',
        'activo',
    ];

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'id_miembro');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Sesiones que dicta el docente. El dictado se asigna sesión por sesión.
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'id_docente');
    }
}
