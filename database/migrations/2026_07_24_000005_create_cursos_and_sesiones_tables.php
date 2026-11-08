<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('f_inicio');
            $table->date('f_fin')->nullable();
            $table->integer('cupo_max')->default(30);
            $table->json('dias_semana')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->boolean('tiene_pago')->default(false);
            $table->decimal('monto_inscripcion', 10, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->date('fecha_inscripcion');
            $table->string('estado')->default('Activo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_curso')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('id_docente')->nullable()->constrained('docentes')->onDelete('set null');
            $table->date('fecha');
            $table->time('hora');
            $table->string('tema');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asistencias_sesion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sesion_id')->constrained('sesiones')->onDelete('cascade');
            $table->string('estado'); // Presente, Ausente, Tardanza, Justificado
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias_sesion');
        Schema::dropIfExists('sesiones');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('cursos');
    }
};
