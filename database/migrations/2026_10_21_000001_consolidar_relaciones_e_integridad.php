<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarDuplicados();

        // 1. Relaciones 1:1 y pivotes sin duplicados silenciosos.
        Schema::table('docentes', function (Blueprint $table) {
            $table->unique('id_miembro', 'docentes_id_miembro_unique');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->unique('pago_curso_id', 'ingresos_pago_curso_id_unique');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->unique(['user_id', 'role_id'], 'role_user_user_id_role_id_unique');
        });

        Schema::table('inscripciones', function (Blueprint $table) {
            $table->unique(['miembro_id', 'curso_id'], 'inscripciones_miembro_id_curso_id_unique');
        });

        // 2. Opción A: pago_cursos es la única fuente de monto, fecha y método de pago.
        //    El ingreso generado por un pago guarda solo pago_curso_id.
        Schema::table('ingresos', function (Blueprint $table) {
            $table->decimal('monto_total', 12, 2)->nullable()->change();
            $table->date('fecha')->nullable()->change();
            $table->string('metodo_pago')->nullable()->change();
        });

        // Los ingresos ya generados por un pago dejan de duplicar los importes:
        // desde ahora se leen de pago_cursos con COALESCE.
        DB::table('ingresos')
            ->whereNotNull('pago_curso_id')
            ->update([
                'monto_total' => null,
                'fecha' => null,
                'metodo_pago' => null,
            ]);

        // 3. Los miembros no tienen usuario: la asistencia no se audita por user_id.
        if (DB::getDriverName() === 'sqlite') {
            $this->quitarUsuarioDeAsistencias();
        } else {
            Schema::table('asistencias_sesion', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('asistencias_sesion', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable();
            });
        } else {
            Schema::table('asistencias_sesion', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('miembro_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        // Se rellenan los valores derivados antes de volver a hacerlos obligatorios.
        DB::table('ingresos')->whereNull('monto_total')->update(['monto_total' => 0]);
        DB::table('ingresos')->whereNull('metodo_pago')->update(['metodo_pago' => 'Efectivo']);
        DB::table('ingresos')->whereNull('fecha')->update(['fecha' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        Schema::table('ingresos', function (Blueprint $table) {
            $table->decimal('monto_total', 12, 2)->nullable(false)->change();
            $table->date('fecha')->nullable(false)->change();
            $table->string('metodo_pago')->nullable(false)->change();
        });

        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropUnique('inscripciones_miembro_id_curso_id_unique');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropUnique('role_user_user_id_role_id_unique');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropUnique('ingresos_pago_curso_id_unique');
        });

        Schema::table('docentes', function (Blueprint $table) {
            $table->dropUnique('docentes_id_miembro_unique');
        });
    }

    /**
     * Aborta con un mensaje claro si los datos actuales impiden crear los UNIQUE.
     */
    private function verificarDuplicados(): void
    {
        $checks = [
            'docentes' => ['id_miembro'],
            'ingresos' => ['pago_curso_id'],
            'role_user' => ['user_id', 'role_id'],
            'inscripciones' => ['miembro_id', 'curso_id'],
        ];

        $problemas = [];

        foreach ($checks as $tabla => $columnas) {
            $duplicados = DB::table($tabla)
                ->select($columnas)
                ->where(function ($query) use ($columnas) {
                    foreach ($columnas as $columna) {
                        $query->whereNotNull($columna);
                    }
                })
                ->groupBy($columnas)
                ->havingRaw('COUNT(*) > 1')
                ->limit(3)
                ->get();

            if ($duplicados->isNotEmpty()) {
                $problemas[] = $tabla.' ('.implode(', ', $columnas).'): '.$duplicados->toJson();
            }
        }

        if ($problemas !== []) {
            throw new RuntimeException(
                "Hay registros duplicados que impiden crear los índices UNIQUE.\n"
                ."Depura esos registros y vuelve a ejecutar la migración:\n- "
                .implode("\n- ", $problemas)
            );
        }
    }

    /**
     * SQLite no permite eliminar una FK: se reconstruye la tabla sin user_id.
     */
    private function quitarUsuarioDeAsistencias(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('asistencias_sesion_sin_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');
            $table->foreignId('sesion_id')->constrained('sesiones')->onDelete('cascade');
            $table->string('estado');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'INSERT INTO asistencias_sesion_sin_usuario '
            .'(id, miembro_id, sesion_id, estado, observacion, created_at, updated_at, deleted_at) '
            .'SELECT id, miembro_id, sesion_id, estado, observacion, created_at, updated_at, deleted_at '
            .'FROM asistencias_sesion'
        );

        Schema::drop('asistencias_sesion');
        Schema::rename('asistencias_sesion_sin_usuario', 'asistencias_sesion');

        Schema::enableForeignKeyConstraints();
    }
};
