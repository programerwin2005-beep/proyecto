<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El docente se asigna a cada sesión (sesiones.id_docente), no al curso
     * completo. La tabla pivote docente_curso quedó en desuso y se elimina.
     */
    public function up(): void
    {
        Schema::dropIfExists('docente_curso');
    }

    public function down(): void
    {
        // No se restaura: la asignación docente-curso ya no forma parte del
        // sistema y volver a crearla dejaría una tabla huérfana sin uso.
    }
};
