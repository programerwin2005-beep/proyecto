<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El líder de un ministerio ahora es siempre un líder de iglesia.
        // Si algún ministerio quedó a cargo de un miembro, se detiene la migración
        // para que ese caso se resuelva manualmente antes de perder el dato.
        $lideresMiembro = DB::table('ministerios')
            ->where('liderable_type', 'App\Models\Miembro')
            ->pluck('nombre');

        if ($lideresMiembro->isNotEmpty()) {
            throw new RuntimeException(
                'Estos ministerios tienen un miembro como líder y deben reasignarse a un líder de iglesia antes de migrar: '
                .$lideresMiembro->implode(', ')
            );
        }

        Schema::table('ministerios', function (Blueprint $table) {
            $table->foreignId('id_lider_iglesia')
                ->nullable()
                ->after('descripcion')
                ->constrained('lideres_iglesia')
                ->nullOnDelete();
        });

        // Conservar los líderes ya asignados antes de eliminar el polimorfismo.
        DB::table('ministerios')
            ->where('liderable_type', 'App\Models\LiderIglesia')
            ->update(['id_lider_iglesia' => DB::raw('liderable_id')]);

        Schema::table('ministerios', function (Blueprint $table) {
            $table->dropMorphs('liderable');
        });

        // El cargo es institucional y vive en lideres_iglesia; el vínculo del
        // miembro con el ministerio solo registra pertenencia y período.
        Schema::table('miembro_ministerio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargo_id');
        });
    }

    public function down(): void
    {
        Schema::table('miembro_ministerio', function (Blueprint $table) {
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
        });

        Schema::table('ministerios', function (Blueprint $table) {
            $table->nullableMorphs('liderable');
        });

        DB::table('ministerios')
            ->whereNotNull('id_lider_iglesia')
            ->update([
                'liderable_type' => 'App\Models\LiderIglesia',
                'liderable_id' => DB::raw('id_lider_iglesia'),
            ]);

        Schema::table('ministerios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_lider_iglesia');
        });
    }
};
