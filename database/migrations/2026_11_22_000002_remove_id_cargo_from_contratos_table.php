<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cargo en los contratos era un dato legacy: el cargo se obtiene del líder
 * de iglesia o del miembro, no del contrato. Se elimina la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contratos', 'id_cargo')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropForeign(['id_cargo']);
            });
        }

        $indices = collect(Schema::getIndexes('contratos'))
            ->filter(fn (array $indice) => in_array('id_cargo', $indice['columns'] ?? [], true));

        foreach ($indices as $indice) {
            Schema::table('contratos', function (Blueprint $table) use ($indice) {
                $table->dropIndex($indice['name']);
            });
        }

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('id_cargo');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('contratos', 'id_cargo')) {
            return;
        }

        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cargo')->nullable()->after('id_miembro')->index();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('id_cargo')->references('id')->on('cargos')->nullOnDelete();
            }
        });
    }
};
