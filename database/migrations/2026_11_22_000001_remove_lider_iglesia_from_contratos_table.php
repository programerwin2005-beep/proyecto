<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los contratos dejan de depender de un líder de iglesia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contratos', 'id_lider_iglesia')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('id_lider_iglesia');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contratos', 'id_lider_iglesia')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreignId('id_lider_iglesia')
                    ->nullable()
                    ->after('id_miembro')
                    ->constrained('lideres_iglesia')
                    ->nullOnDelete();
            });
        }
    }
};
