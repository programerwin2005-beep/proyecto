<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte el vínculo líder–miembro de 2026_10_28_000005.
 *
 * `lideres_iglesia` y `miembros` vuelven a ser entidades independientes:
 * se elimina la columna `id_miembro`, su UNIQUE y su FK. El líder sigue
 * siendo el único que puede estar a cargo de un ministerio
 * (`ministerios.id_lider_iglesia`); los miembros solo integran ministerios
 * mediante el pivote `miembro_ministerio`.
 *
 * El UNIQUE del pivote `(miembro_id, ministerio_id)` se conserva, igual que
 * la restauración de vínculos con `App\Support\VinculoPivote`.
 */
return new class extends Migration
{
    private const UNIQUE_LIDER = 'lideres_iglesia_id_miembro_unique';

    private const FK_LIDER = 'lideres_iglesia_id_miembro_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('lideres_iglesia')
            || ! Schema::hasColumn('lideres_iglesia', 'id_miembro')) {
            return;
        }

        $this->avisarVinculosExistentes();

        // La FK debe caer antes que el UNIQUE que la respalda.
        if (DB::getDriverName() !== 'sqlite'
            && $this->tieneForeignKey('lideres_iglesia', self::FK_LIDER)) {
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

    public function down(): void
    {
        if (! Schema::hasTable('lideres_iglesia')
            || Schema::hasColumn('lideres_iglesia', 'id_miembro')) {
            return;
        }

        Schema::table('lideres_iglesia', function (Blueprint $table) {
            $table->unsignedBigInteger('id_miembro')->nullable()->after('id_cargo');
        });

        if (! Schema::hasIndex('lideres_iglesia', self::UNIQUE_LIDER)) {
            Schema::table('lideres_iglesia', function (Blueprint $table) {
                $table->unique('id_miembro', self::UNIQUE_LIDER);
            });
        }

        // SQLite no permite agregar una FK con ALTER TABLE; en ese motor la
        // integridad queda a cargo de la aplicación.
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
     * Avisa qué vínculos se descartan al eliminar la columna. No aborta:
     * quitar la columna no está impedido por los datos, pero conviene
     * respaldarlos y conservarlos en `nombre`/`telefono` del líder.
     */
    private function avisarVinculosExistentes(): void
    {
        $vinculados = DB::table('lideres_iglesia')
            ->whereNotNull('id_miembro')
            ->orderBy('id')
            ->get(['id', 'nombre', 'id_miembro']);

        if ($vinculados->isEmpty()) {
            $this->aviso('No hay líderes vinculados a miembros; se elimina la columna sin descartar vínculos.');

            return;
        }

        $this->aviso('Se eliminará lideres_iglesia.id_miembro. Vínculos que se descartan: '.$vinculados->count());
        $this->aviso('SELECT id, nombre, id_miembro FROM lideres_iglesia WHERE id_miembro IS NOT NULL ORDER BY id;');
        $this->aviso($vinculados->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->aviso('Respalda estos datos antes de continuar. El nombre y el teléfono del líder se conservan en sus propias columnas.');
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
