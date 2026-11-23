<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_actividades', function (Blueprint $table) {
            // NULL identifica avances históricos todavía no revisados como tareas.
            $table->string('nombre_tarea')->nullable();
        });

        // Ningún avance histórico tiene todavía un nombre revisado. No inferir
        // tareas independientes ni cerrar eventos a partir de esos porcentajes.
        DB::table('actividades')->whereNull('deleted_at')
            ->whereNotIn('estado', ['Cancelada', 'Cancelado'])
            ->update(['estado' => DB::raw("CASE WHEN EXISTS (
                SELECT 1 FROM seguimiento_actividades
                WHERE seguimiento_actividades.id_actividad = actividades.id
                  AND seguimiento_actividades.deleted_at IS NULL
            ) THEN 'En Progreso' ELSE 'Programado' END")]);
    }

    public function down(): void
    {
        Schema::table('seguimiento_actividades', fn (Blueprint $table) => $table->dropColumn('nombre_tarea'));
    }
};
