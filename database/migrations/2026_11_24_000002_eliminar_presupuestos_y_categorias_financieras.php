<?php

use App\Enums\TipoEgreso;
use App\Enums\TipoIngreso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el presupuesto y el catálogo de categorías financieras.
 *
 * Las categorías pasan a vivir en los enums `TipoIngreso` y `TipoEgreso`, que
 * ya alimentan los formularios de ingresos y egresos. Antes de eliminar el
 * catálogo se copia su nombre a `ingresos.tipo` / `egresos.tipo_egreso` cuando
 * esos campos estuvieran vacíos, para no perder ningún registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No se gestiona presupuesto: la tabla solo existía como trabajo futuro.
        Schema::dropIfExists('presupuestos');

        $this->volcarCategoriasEnTexto();

        $this->quitarCategoria('ingresos');
        $this->quitarCategoria('egresos');

        Schema::dropIfExists('categorias_financieras');
    }

    public function down(): void
    {
        if (! Schema::hasTable('categorias_financieras')) {
            Schema::create('categorias_financieras', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('naturaleza', 10);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['nombre', 'naturaleza'], 'categorias_financieras_nombre_naturaleza_unique');
            });
        }

        $this->sembrarCategorias();

        $this->restaurarCategoria('ingresos', 'tipo', 'idx_ingresos_categoria', 'ingreso');
        $this->restaurarCategoria('egresos', 'tipo_egreso', 'idx_egresos_categoria', 'egreso');

        if (! Schema::hasTable('presupuestos')) {
            Schema::create('presupuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->unsignedBigInteger('categoria_id');
                $table->decimal('monto_planificado', 12, 2);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['anio', 'mes', 'categoria_id'], 'presupuestos_anio_mes_categoria_unique');
                $table->foreign('categoria_id')->references('id')->on('categorias_financieras')->cascadeOnDelete();
            });
        }
    }

    private function volcarCategoriasEnTexto(): void
    {
        if (! Schema::hasTable('categorias_financieras')) {
            return;
        }

        if (Schema::hasTable('ingresos')
            && Schema::hasColumn('ingresos', 'categoria_id')
            && Schema::hasColumn('ingresos', 'tipo')) {
            foreach (DB::table('categorias_financieras')->get(['id', 'nombre']) as $categoria) {
                DB::table('ingresos')
                    ->where('categoria_id', $categoria->id)
                    ->where(fn ($query) => $query->whereNull('tipo')->orWhere('tipo', ''))
                    ->update(['tipo' => $categoria->nombre]);
            }
        }

        if (Schema::hasTable('egresos')
            && Schema::hasColumn('egresos', 'categoria_id')
            && Schema::hasColumn('egresos', 'tipo_egreso')) {
            foreach (DB::table('categorias_financieras')->get(['id', 'nombre']) as $categoria) {
                DB::table('egresos')
                    ->where('categoria_id', $categoria->id)
                    ->where(fn ($query) => $query->whereNull('tipo_egreso')->orWhere('tipo_egreso', ''))
                    ->update(['tipo_egreso' => $categoria->nombre]);
            }
        }
    }

    private function quitarCategoria(string $tabla): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'categoria_id')) {
            return;
        }

        // SQLite no permite eliminar una columna mientras exista un índice que
        // la use, y en estas tablas hay más de un índice sobre `categoria_id`.
        // Se eliminan todos los índices que la incluyan antes de quitar la
        // columna.
        foreach (Schema::getIndexes($tabla) as $indice) {
            if (in_array('categoria_id', $indice['columns'], true)) {
                Schema::table($tabla, fn (Blueprint $blueprint) => $blueprint->dropIndex($indice['name']));
            }
        }

        Schema::table($tabla, function (Blueprint $blueprint) {
            $blueprint->dropColumn('categoria_id');
        });
    }

    private function sembrarCategorias(): void
    {
        $definiciones = [
            'ingreso' => TipoIngreso::valores(),
            'egreso' => TipoEgreso::valores(),
        ];

        foreach ($definiciones as $naturaleza => $nombres) {
            foreach ($nombres as $nombre) {
                $existente = DB::table('categorias_financieras')
                    ->where('nombre', $nombre)
                    ->where('naturaleza', $naturaleza)
                    ->first();

                if ($existente) {
                    DB::table('categorias_financieras')
                        ->where('id', $existente->id)
                        ->update([
                            'activo' => true,
                            'deleted_at' => null,
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('categorias_financieras')->insert([
                    'nombre' => $nombre,
                    'naturaleza' => $naturaleza,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function restaurarCategoria(string $tabla, string $columna, string $indice, string $naturaleza): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        if (! Schema::hasColumn($tabla, 'categoria_id')) {
            Schema::table($tabla, function (Blueprint $blueprint) use ($indice) {
                $blueprint->unsignedBigInteger('categoria_id')->nullable();
                $blueprint->index('categoria_id', $indice);
            });

            // La migración original no crea la clave foránea en SQLite, porque
            // su rollback posterior elimina la columna y SQLite no permite
            // quitar una columna que participa en una clave foránea.
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($tabla, function (Blueprint $blueprint) {
                    $blueprint->foreign('categoria_id')
                        ->references('id')
                        ->on('categorias_financieras')
                        ->nullOnDelete();
                });
            }
        }

        $categorias = DB::table('categorias_financieras')
            ->where('naturaleza', $naturaleza)
            ->get(['id', 'nombre']);

        foreach ($categorias as $categoria) {
            DB::table($tabla)
                ->where($columna, $categoria->nombre)
                ->whereNull('categoria_id')
                ->update(['categoria_id' => $categoria->id]);
        }
    }
};
