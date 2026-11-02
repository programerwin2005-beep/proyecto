<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opción A: una persona existe una sola vez en `miembros`.
 *
 * `lideres_iglesia` pasa a ser el rol de liderazgo sobre un miembro
 * (`id_miembro` nullable y UNIQUE). `nombre` y `telefono` se conservan como
 * respaldo legacy para los líderes que todavía no están vinculados.
 *
 * Además se agrega el UNIQUE del pivote `miembro_ministerio`: un miembro no
 * puede repetirse dentro del mismo ministerio. El índice incluye las filas
 * con `deleted_at`, por eso la aplicación restaura el vínculo en lugar de
 * insertar otra fila (ver App\Support\VinculoPivote).
 */
return new class extends Migration
{
    private const UNIQUE_LIDER = 'lideres_iglesia_id_miembro_unique';

    private const FK_LIDER = 'lideres_iglesia_id_miembro_foreign';

    private const UNIQUE_PIVOTE = 'miembro_ministerio_miembro_id_ministerio_id_unique';

    public function up(): void
    {
        $this->agregarIdMiembro();
        $this->vincularLideresPorNombre();
        $this->agregarUniqueAlPivote();
    }

    public function down(): void
    {
        if (Schema::hasTable('miembro_ministerio')
            && Schema::hasIndex('miembro_ministerio', self::UNIQUE_PIVOTE)) {
            Schema::table('miembro_ministerio', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_PIVOTE);
            });
        }

        if (! Schema::hasTable('lideres_iglesia')
            || ! Schema::hasColumn('lideres_iglesia', 'id_miembro')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite' && $this->tieneForeignKey('lideres_iglesia', self::FK_LIDER)) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->dropForeign(self::FK_LIDER);
            });
        }

        if (Schema::hasIndex('lideres_iglesia', self::UNIQUE_LIDER)) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_LIDER);
            });
        }

        Schema::table('lideres_iglesia', function (Blueprint $table) {
            $table->dropColumn('id_miembro');
        });
    }

    /**
     * Agrega la columna, su UNIQUE y la FK. Es idempotente para poder
     * reejecutarse si una corrida anterior quedó a medias.
     */
    private function agregarIdMiembro(): void
    {
        if (! Schema::hasColumn('lideres_iglesia', 'id_miembro')) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->unsignedBigInteger('id_miembro')->nullable()->after('id_cargo');
            });
        }

        $this->verificarDuplicadosDeMiembro();

        if (! Schema::hasIndex('lideres_iglesia', self::UNIQUE_LIDER)) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->unique('id_miembro', self::UNIQUE_LIDER);
            });
        }

        // SQLite no permite agregar una FK con ALTER TABLE. La integridad se
        // mantiene desde la aplicación y el UNIQUE sigue vigente.
        if (DB::getDriverName() !== 'sqlite'
            && ! $this->tieneForeignKey('lideres_iglesia', self::FK_LIDER)) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->foreign('id_miembro', self::FK_LIDER)
                    ->references('id')
                    ->on('miembros')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Vincula únicamente coincidencias exactas y no ambiguas de nombre entre
     * un líder y un miembro. No crea miembros nuevos ni adivina identidades.
     */
    private function vincularLideresPorNombre(): void
    {
        $lideres = DB::table('lideres_iglesia')
            ->whereNull('deleted_at')
            ->whereNull('id_miembro')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        if ($lideres->isEmpty()) {
            return;
        }

        $this->aviso('Backfill de lideres_iglesia.id_miembro por nombre exacto.');
        $this->aviso('Respalda la base de datos antes de continuar: la migración actualiza filas existentes.');

        $vinculados = [];
        $ambiguos = [];
        $sinCoincidencia = [];

        foreach ($lideres as $lider) {
            $nombre = mb_strtolower(trim((string) $lider->nombre));

            $candidatos = DB::table('miembros')
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [$nombre])
                ->get(['id', 'nombre']);

            if ($candidatos->count() === 1) {
                $miembro = $candidatos->first();

                $ocupado = DB::table('lideres_iglesia')
                    ->where('id_miembro', $miembro->id)
                    ->where('id', '<>', $lider->id)
                    ->exists();

                if ($ocupado) {
                    $ambiguos[] = "líder #{$lider->id} ({$lider->nombre}): el miembro #{$miembro->id} ya está vinculado a otro líder";

                    continue;
                }

                DB::table('lideres_iglesia')
                    ->where('id', $lider->id)
                    ->update(['id_miembro' => $miembro->id]);

                $vinculados[] = "líder #{$lider->id} ({$lider->nombre}) → miembro #{$miembro->id}";

                continue;
            }

            if ($candidatos->count() > 1) {
                $ids = $candidatos->pluck('id')->implode(', ');
                $ambiguos[] = "líder #{$lider->id} ({$lider->nombre}): varios miembros con el mismo nombre (ids: {$ids})";

                continue;
            }

            $sinCoincidencia[] = "líder #{$lider->id} ({$lider->nombre})";
        }

        $this->aviso('Vinculados: '.count($vinculados));
        foreach ($vinculados as $linea) {
            $this->aviso('  - '.$linea);
        }

        $this->aviso('Sin coincidencia: '.count($sinCoincidencia));
        foreach ($sinCoincidencia as $linea) {
            $this->aviso('  - '.$linea);
        }

        if ($ambiguos !== []) {
            $this->aviso('No vinculados por ambigüedad o duplicidad: '.count($ambiguos));
            foreach ($ambiguos as $linea) {
                $this->aviso('  - '.$linea);
            }
        }
    }

    /**
     * Aborta si el UNIQUE de lideres_iglesia.id_miembro no se puede crear.
     */
    private function verificarDuplicadosDeMiembro(): void
    {
        $duplicados = DB::table('lideres_iglesia')
            ->select('id_miembro', DB::raw('COUNT(*) AS total'))
            ->whereNotNull('id_miembro')
            ->groupBy('id_miembro')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicados->isEmpty()) {
            return;
        }

        $ids = $duplicados->pluck('id_miembro')->all();
        $filas = DB::table('lideres_iglesia')
            ->whereIn('id_miembro', $ids)
            ->orderBy('id_miembro')
            ->orderBy('id')
            ->get(['id', 'id_miembro', 'nombre', 'deleted_at']);

        $this->aviso('SELECT id, id_miembro, nombre, deleted_at FROM lideres_iglesia WHERE id_miembro IN ('
            .implode(', ', $ids).') ORDER BY id_miembro, id;');
        $this->aviso($filas->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        throw new RuntimeException(
            "Hay líderes que comparten el mismo miembro y no se puede crear el UNIQUE.\n"
            .'Respalda la base de datos y depura esos registros antes de volver a ejecutar la migración.'
        );
    }

    /**
     * Agrega el UNIQUE del pivote. Cuenta también las filas con `deleted_at`
     * porque el índice las incluye.
     */
    private function agregarUniqueAlPivote(): void
    {
        if (Schema::hasIndex('miembro_ministerio', self::UNIQUE_PIVOTE)) {
            return;
        }

        $duplicados = DB::table('miembro_ministerio')
            ->select('miembro_id', 'ministerio_id', DB::raw('COUNT(*) AS total'))
            ->groupBy('miembro_id', 'ministerio_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicados->isNotEmpty()) {
            $filas = DB::table('miembro_ministerio')
                ->whereIn('miembro_id', $duplicados->pluck('miembro_id'))
                ->orderBy('miembro_id')
                ->orderBy('ministerio_id')
                ->orderBy('id')
                ->get(['id', 'miembro_id', 'ministerio_id', 'fecha_inicio', 'fecha_fin', 'deleted_at']);

            $this->aviso('SELECT id, miembro_id, ministerio_id, fecha_inicio, fecha_fin, deleted_at');
            $this->aviso('FROM miembro_ministerio ORDER BY miembro_id, ministerio_id, id;');
            $this->aviso($filas->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            throw new RuntimeException(
                "Hay vínculos duplicados en miembro_ministerio y no se puede crear el UNIQUE (miembro_id, ministerio_id).\n"
                .'Respalda la base de datos y conserva una sola fila por par antes de volver a ejecutar la migración.'
            );
        }

        Schema::table('miembro_ministerio', function (Blueprint $table) {
            $table->unique(['miembro_id', 'ministerio_id'], self::UNIQUE_PIVOTE);
        });
    }

    private function tieneForeignKey(string $tabla, string $nombre): bool
    {
        foreach (Schema::getForeignKeys($tabla) as $foreign) {
            if (($foreign['name'] ?? null) === $nombre) {
                return true;
            }
        }

        return false;
    }

    private function aviso(string $mensaje): void
    {
        echo $mensaje.PHP_EOL;
    }
};
