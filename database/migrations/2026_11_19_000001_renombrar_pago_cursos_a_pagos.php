<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pago_cursos')) {
            if (Schema::hasTable('pagos')) {
                throw new RuntimeException('Existen ambas tablas. Revisar antes de continuar.');
            }

            Schema::rename('pago_cursos', 'pagos');
        }

        if (Schema::hasColumn('ingresos', 'pago_curso_id')) {
            if (Schema::hasColumn('ingresos', 'pago_id')) {
                throw new RuntimeException('Existen ambas columnas. Revisar antes de continuar.');
            }

            Schema::table('ingresos', function (Blueprint $table) {
                $table->renameColumn('pago_curso_id', 'pago_id');
            });
        }

        if (! Schema::hasTable('pagos') ||
            ! Schema::hasColumn('ingresos', 'pago_id')) {
            throw new RuntimeException('La estructura final no es la esperada.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión requiere revisar la versión del código.');
    }
};
