<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los índices UNIQUE creados en migraciones anteriores incluyen las filas
     * con `deleted_at`. Esta migración no modifica el esquema: solo verifica
     * que no existan duplicados (contando registros eliminados) que impidan
     * restaurar un registro cuando se vuelva a crear la misma relación.
     *
     * La restauración se resuelve en código con `withTrashed()` + `restore()`.
     */
    public function up(): void
    {
        $this->verificarDuplicadosIncluyendoEliminados();
    }

    public function down(): void
    {
        // No hay cambios de esquema que revertir. La comprobación es solo de
        // datos y se puede volver a ejecutar en cualquier momento.
    }

    private function verificarDuplicadosIncluyendoEliminados(): void
    {
        $checks = [
            'asistencias_sesion' => ['sesion_id', 'miembro_id'],
            'inscripciones' => ['miembro_id', 'curso_id'],
            'role_user' => ['user_id', 'role_id'],
            'docentes' => ['id_miembro'],
            'ingresos' => ['pago_curso_id'],
        ];

        $problemas = [];

        foreach ($checks as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue 2;
                }
            }

            $duplicados = DB::table($tabla)
                ->select($columnas)
                ->where(function ($query) use ($columnas) {
                    foreach ($columnas as $columna) {
                        $query->whereNotNull($columna);
                    }
                })
                ->groupBy($columnas)
                ->havingRaw('COUNT(*) > 1')
                ->limit(5)
                ->get();

            if ($duplicados->isNotEmpty()) {
                $problemas[] = $tabla.' ('.implode(', ', $columnas).'): '.$duplicados->toJson();
            }
        }

        if ($problemas !== []) {
            throw new RuntimeException(
                'Hay registros duplicados (incluyendo eliminados) que impiden restaurar '
                ."registros únicos.\nRespalda la base de datos y depura esos grupos antes de "
                ."volver a ejecutar la migración:\n- ".implode("\n- ", $problemas)
            );
        }
    }
};
