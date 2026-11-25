<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $acciones = [
        'asignar_roles' => ['roles', 'editar'],
        'gestionar_acceso' => ['docentes', 'editar'],
        'generar_sesiones' => ['sesiones', 'crear'],
        'marcar_todos' => ['asistencia', 'crear'],
        'ver_historial' => ['asistencia', 'ver'],
        'completar' => ['seguimiento', 'editar'],
        'renovar' => ['contratos', 'editar'],
        'pagar_contrato' => ['egresos', 'crear'],
        'ingreso_actividad' => ['ingresos', 'crear'],
    ];

    public function up(): void
    {
        Schema::table('permisos', function (Blueprint $table) {
            foreach ($this->acciones as $accion => $origen) {
                $table->boolean($accion)->default(false);
            }
        });

        // Conserva los accesos actuales al separar las acciones generales.
        foreach ($this->acciones as $accion => [$modulo, $hereda]) {
            DB::table('permisos')->where('modulo', $modulo)
                ->update([$accion => DB::raw($hereda)]);
        }
    }

    public function down(): void
    {
        Schema::table('permisos', fn (Blueprint $table) => $table->dropColumn(array_keys($this->acciones)));
    }
};
