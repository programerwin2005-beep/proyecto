<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('egresos', 'periodo_inicio')) {
            Schema::table('egresos', function (Blueprint $table) {
                $table->date('periodo_inicio')->nullable()->after('fecha');
                $table->index('periodo_inicio');
            });
        }
    }

    public function down(): void
    {
        // El periodo forma parte del esquema actual; no se elimina al revertir.
    }
};
