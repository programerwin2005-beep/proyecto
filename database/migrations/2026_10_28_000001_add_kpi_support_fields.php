<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Membresía: bajas y fechas de crecimiento espiritual.
        $this->addColumn('miembros', 'fecha_baja', fn (Blueprint $t) => $t->date('fecha_baja')->nullable()->after('fecha_ingreso'));
        $this->addColumn('miembros', 'motivo_baja', fn (Blueprint $t) => $t->string('motivo_baja')->nullable()->after('fecha_baja'));
        $this->addColumn('miembros', 'fecha_conversion', fn (Blueprint $t) => $t->date('fecha_conversion')->nullable()->after('fecha_ingreso'));
        $this->addColumn('miembros', 'fecha_bautismo', fn (Blueprint $t) => $t->date('fecha_bautismo')->nullable()->after('fecha_conversion'));

        // 2. Deriva de esquema: columnas que existen en la BD viva pero no en migraciones.
        if (! Schema::hasColumn('contratos', 'id_cargo')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_cargo')->nullable()->after('id_miembro')->index();
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('id_cargo')->references('id')->on('cargos')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasColumn('egresos', 'id_lider_iglesia')) {
            Schema::table('egresos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_lider_iglesia')->nullable()->after('id_contrato')->index();
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('id_lider_iglesia')->references('id')->on('lideres_iglesia')->nullOnDelete();
                }
            });
        }

        // 3. Cobranza: fecha de vencimiento real.
        $this->addColumn('inscripciones', 'fecha_vencimiento', fn (Blueprint $t) => $t->date('fecha_vencimiento')->nullable()->after('fecha_inscripcion'));

        // 4. Actividades: fin, presupuesto y costo real.
        $this->addColumn('actividades', 'fecha_fin', fn (Blueprint $t) => $t->date('fecha_fin')->nullable()->after('fecha'));
        $this->addColumn('actividades', 'presupuesto', fn (Blueprint $t) => $t->decimal('presupuesto', 12, 2)->nullable()->after('fecha_fin'));
        $this->addColumn('actividades', 'costo_real', fn (Blueprint $t) => $t->decimal('costo_real', 12, 2)->nullable()->after('presupuesto'));

        // 5. Seguridad y uso.
        $this->addColumn('users', 'last_login_at', fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable()->after('remember_token'));

        // 6. Catálogo financiero normalizado.
        if (! Schema::hasTable('categorias_financieras')) {
            Schema::create('categorias_financieras', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('naturaleza', 10); // ingreso | egreso
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['nombre', 'naturaleza'], 'categorias_financieras_nombre_naturaleza_unique');
            });
        }

        $this->addForeignColumn('ingresos', 'categoria_id', 'categorias_financieras', 'tipo');
        $this->addForeignColumn('egresos', 'categoria_id', 'categorias_financieras', 'tipo_egreso');

        // 7. Presupuesto mensual por categoría.
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

        // 8. Asistencia a cultos.
        if (! Schema::hasTable('asistencias_culto')) {
            Schema::create('asistencias_culto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('miembro_id')->constrained('miembros')->cascadeOnDelete();
                $table->foreignId('culto_id')->constrained('cultos')->cascadeOnDelete();
                $table->date('fecha');
                $table->string('estado')->default('Presente');
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['miembro_id', 'culto_id', 'fecha'], 'asistencias_culto_miembro_culto_fecha_unique');
                $table->index(['culto_id', 'fecha'], 'asistencias_culto_culto_fecha_index');
            });
        }

        // 9. Auditoría de uso por módulo.
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('modulo', 100);
                $table->string('accion', 50);
                $table->string('entidad_type')->nullable();
                $table->unsignedBigInteger('entidad_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index(['modulo', 'created_at'], 'audit_logs_modulo_created_at_index');
                $table->index(['user_id', 'created_at'], 'audit_logs_user_id_created_at_index');
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        // 10. Snapshots de calidad y seguridad.
        if (! Schema::hasTable('metricas_calidad')) {
            Schema::create('metricas_calidad', function (Blueprint $table) {
                $table->id();
                $table->string('fuente', 50);
                $table->string('metrica', 100);
                $table->decimal('valor', 14, 4)->nullable();
                $table->string('texto', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('capturado_en');
                $table->timestamps();
                $table->index(['fuente', 'metrica', 'capturado_en'], 'metricas_calidad_fuente_metrica_fecha_index');
            });
        }

        if (! Schema::hasTable('hallazgos_seguridad')) {
            Schema::create('hallazgos_seguridad', function (Blueprint $table) {
                $table->id();
                $table->string('fuente', 50);
                $table->string('severidad', 20);
                $table->string('estado', 30)->default('abierto');
                $table->string('regla', 255)->nullable();
                $table->string('archivo', 500)->nullable();
                $table->unsignedInteger('linea')->nullable();
                $table->string('fingerprint', 64)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['severidad', 'estado'], 'hallazgos_seguridad_severidad_estado_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hallazgos_seguridad');
        Schema::dropIfExists('metricas_calidad');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('asistencias_culto');
        Schema::dropIfExists('presupuestos');

        // SQLite no elimina el índice al quitar la columna: hay que quitarlo antes.
        $this->dropIndexIfExists('egresos', 'egresos_categoria_id_index');
        $this->dropIndexIfExists('ingresos', 'ingresos_categoria_id_index');
        $this->dropForeignIfExists('egresos', 'categoria_id');
        $this->dropForeignIfExists('ingresos', 'categoria_id');
        $this->dropColumnIfExists('egresos', 'categoria_id');
        $this->dropColumnIfExists('ingresos', 'categoria_id');
        Schema::dropIfExists('categorias_financieras');

        $this->dropColumnIfExists('users', 'last_login_at');
        $this->dropColumnIfExists('actividades', 'costo_real');
        $this->dropColumnIfExists('actividades', 'presupuesto');
        $this->dropColumnIfExists('actividades', 'fecha_fin');
        $this->dropColumnIfExists('inscripciones', 'fecha_vencimiento');
        $this->dropColumnIfExists('miembros', 'fecha_bautismo');
        $this->dropColumnIfExists('miembros', 'fecha_conversion');
        $this->dropColumnIfExists('miembros', 'motivo_baja');
        $this->dropColumnIfExists('miembros', 'fecha_baja');

        // No se eliminan contratos.id_cargo ni egresos.id_lider_iglesia:
        // pueden existir en la BD viva antes de esta migración y contener datos.
    }

    private function addColumn(string $table, string $column, Closure $callback): void
    {
        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, $callback);
        }
    }

    private function addForeignColumn(string $table, string $column, string $references, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table, $column, $references, $after) {
            $definition = $t->unsignedBigInteger($column)->nullable();
            if ($after && DB::getDriverName() !== 'sqlite') {
                $definition->after($after);
            }
            $t->index($column, "{$table}_{$column}_index");
            if (DB::getDriverName() !== 'sqlite') {
                $t->foreign($column)->references('id')->on($references)->nullOnDelete();
            }
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        } catch (Throwable) {
            // La FK pudo no haber sido creada por esta migración.
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
        }
    }
};
