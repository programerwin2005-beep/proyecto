<?php

namespace App\Models;

use App\Casts\EnumTolerante;
use App\Enums\EstadoActividad;
use App\Enums\EstadoSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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

    /**
     * Nombres que pueden figurar como responsables de una tarea: el líder del
     * ministerio y sus miembros. No crea relaciones nuevas, solo deriva la
     * lista del vínculo que ya existe entre actividad, ministerio y personas.
     *
     * @return Collection<int, string>
     */
    public function nombresResponsables(): Collection
    {
        $this->loadMissing('ministerio.lider', 'ministerio.miembros');

        return collect([$this->ministerio?->lider?->nombre])
            ->merge($this->ministerio?->miembros?->pluck('nombre') ?? [])
            ->filter(fn ($nombre) => filled($nombre))
            ->map(fn ($nombre) => trim((string) $nombre))
            ->unique()
            ->values();
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
