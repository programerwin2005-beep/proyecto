<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un miembro pertenece a un solo ministerio.
 *
 * La relación deja de ser muchos-a-muchos: `miembros.id_ministerio` guarda el
 * ministerio actual y la tabla pivote `miembro_ministerio` se elimina. Al
 * migrar los datos se conserva el vínculo vigente más reciente de cada miembro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('miembros', 'id_ministerio')) {
            Schema::table('miembros', function (Blueprint $table) {
                $table->foreignId('id_ministerio')
                    ->nullable()
                    ->after('estado')
                    ->constrained('ministerios')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('miembro_ministerio')) {
            return;
        }

        $vinculos = DB::table('miembro_ministerio as mm')
            ->join('ministerios as m', 'm.id', '=', 'mm.ministerio_id')
            ->whereNull('mm.deleted_at')
            ->whereNull('m.deleted_at')
            ->orderByDesc('mm.fecha_inicio')
            ->orderByDesc('mm.id')
            ->get(['mm.miembro_id', 'mm.ministerio_id']);

        $asignados = [];

        foreach ($vinculos as $vinculo) {
            if (isset($asignados[$vinculo->miembro_id])) {
                continue;
            }

            $asignados[$vinculo->miembro_id] = true;

            DB::table('miembros')
                ->where('id', $vinculo->miembro_id)
                ->whereNull('id_ministerio')
                ->update(['id_ministerio' => $vinculo->ministerio_id]);
        }

        Schema::drop('miembro_ministerio');
    }

    public function down(): void
    {
        if (! Schema::hasTable('miembro_ministerio')) {
            Schema::create('miembro_ministerio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');
                $table->foreignId('ministerio_id')->constrained('ministerios')->onDelete('cascade');
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['miembro_id', 'ministerio_id']);
            });
        }

        if (Schema::hasColumn('miembros', 'id_ministerio')) {
            $miembros = DB::table('miembros')
                ->whereNotNull('id_ministerio')
                ->get(['id', 'id_ministerio']);

            foreach ($miembros as $miembro) {
                DB::table('miembro_ministerio')->insert([
                    'miembro_id' => $miembro->id,
                    'ministerio_id' => $miembro->id_ministerio,
                    'fecha_inicio' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('miembros', function (Blueprint $table) {
                $table->dropConstrainedForeignId('id_ministerio');
            });
        }
    }
};
