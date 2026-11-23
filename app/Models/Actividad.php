<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\EstadoActividad;
use App\Enums\EstadoSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Actividad extends Model
{
    use SoftDeletes;

    protected $table = 'actividades';

    protected $fillable = [
        'id_ministerio',
        'nombre',
        'fecha',
        'lugar',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EnumTolerante::class.':'.EstadoActividad::class,
        ];
    }

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class, 'id_ministerio');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoActividad::class, 'id_actividad');
    }

    public function estadoVisible(): string
    {
        if ($this->estado === EstadoActividad::Cancelada) {
            return 'Cancelada';
        }
        $tareas = $this->seguimientos;
        if ($tareas->isEmpty()) {
            return 'Programado';
        }
        if ($tareas->every(fn ($t) => filled($t->nombre_tarea) && $t->estado === EstadoSeguimiento::Completado)) {
            return 'Terminado';
        }

        return $this->estado === EstadoActividad::Completada ? 'En Progreso' : ($this->estado?->value ?? 'Programado');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'id_actividad');
    }
}
