<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula cada docente con un usuario de acceso y registra qué usuario
     * tomó la asistencia.
     */
    public function up(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unique('user_id', 'docentes_user_id_unique');
        });

        Schema::table('asistencias_sesion', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->index('user_id', 'asistencias_sesion_user_id_index');
        });

        // SQLite no admite agregar claves foráneas a tablas existentes.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('docentes', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('asistencias_sesion', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('asistencias_sesion', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('docentes', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::table('asistencias_sesion', function (Blueprint $table) {
            $table->dropIndex('asistencias_sesion_user_id_index');
            $table->dropColumn('user_id');
        });

        Schema::table('docentes', function (Blueprint $table) {
            $table->dropUnique('docentes_user_id_unique');
            $table->dropColumn('user_id');
        });
    }
};
