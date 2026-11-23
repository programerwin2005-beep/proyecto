<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miembros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->date('f_nacimiento')->nullable();
            $table->string('sexo', 10)->nullable();
            $table->date('fecha_ingreso');
            $table->string('estado')->default('Activo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_miembro')->constrained('miembros')->onDelete('cascade');
            $table->string('especialidad')->nullable();
            $table->string('titulo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_miembro')->constrained('miembros')->onDelete('cascade');
            $table->string('tipo_compensacion')->default('Salario');
            $table->decimal('salario', 10, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('miembros');
    }
};
