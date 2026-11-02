<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarAsistenciasDuplicadas();

        $this->index('miembros', ['estado', 'fecha_ingreso'], 'idx_miembros_estado_ingreso');
        $this->index('miembros', ['f_nacimiento'], 'idx_miembros_f_nacimiento');
        $this->index('miembros', ['sexo'], 'idx_miembros_sexo');

        $this->index('ingresos', ['fecha'], 'idx_ingresos_fecha');
        $this->index('ingresos', ['id_culto', 'fecha'], 'idx_ingresos_culto_fecha');
        $this->index('ingresos', ['id_actividad'], 'idx_ingresos_actividad');
        $this->index('ingresos', ['tipo'], 'idx_ingresos_tipo');
        $this->index('ingresos', ['categoria_id'], 'idx_ingresos_categoria');

        $this->index('egresos', ['fecha'], 'idx_egresos_fecha');
        $this->index('egresos', ['id_contrato'], 'idx_egresos_contrato');
        $this->index('egresos', ['id_lider_iglesia'], 'idx_egresos_lider');
        $this->index('egresos', ['tipo_egreso'], 'idx_egresos_tipo');
        $this->index('egresos', ['categoria_id'], 'idx_egresos_categoria');

        $this->unique('asistencias_sesion', ['sesion_id', 'miembro_id'], 'uq_asistencias_sesion_miembro');
        $this->index('asistencias_sesion', ['miembro_id'], 'idx_asistencias_miembro');
        $this->index('asistencias_sesion', ['sesion_id', 'estado'], 'idx_asistencias_sesion_estado');

        $this->index('sesiones', ['id_curso', 'fecha'], 'idx_sesiones_curso_fecha');
        $this->index('sesiones', ['id_docente', 'fecha'], 'idx_sesiones_docente_fecha');
        $this->index('inscripciones', ['curso_id', 'estado'], 'idx_inscripciones_curso_estado');
        $this->index('inscripciones', ['fecha_vencimiento'], 'idx_inscripciones_vencimiento');
        $this->index('pago_cursos', ['inscripcion_id', 'fecha_pago'], 'idx_pagos_inscripcion_fecha');
        $this->index('pago_cursos', ['fecha_pago'], 'idx_pagos_fecha');

        $this->index('actividades', ['fecha', 'estado'], 'idx_actividades_fecha_estado');
        $this->index('actividades', ['id_ministerio', 'fecha'], 'idx_actividades_ministerio_fecha');
        $this->index('seguimiento_actividades', ['id_actividad', 'fecha_registro'], 'idx_seguimiento_actividad_fecha');

        $this->index('contratos', ['id_miembro', 'fecha_inicio', 'fecha_fin'], 'idx_contratos_miembro_fechas');
        $this->index('contratos', ['fecha_fin', 'activo'], 'idx_contratos_fin_activo');
        $this->index('contratos', ['id_cargo'], 'idx_contratos_cargo');

        $this->index('miembro_ministerio', ['ministerio_id', 'deleted_at'], 'idx_miembro_ministerio_ministerio');
        $this->index('miembro_ministerio', ['miembro_id', 'fecha_fin'], 'idx_miembro_ministerio_miembro');

        $this->index('roles', ['nombre'], 'idx_roles_nombre');
        $this->index('users', ['last_login_at'], 'idx_users_last_login');
        $this->index('audit_logs', ['modulo', 'created_at'], 'idx_audit_logs_modulo_fecha');
        $this->index('audit_logs', ['user_id', 'created_at'], 'idx_audit_logs_user_fecha');
        $this->index('metricas_calidad', ['fuente', 'metrica', 'capturado_en'], 'idx_metricas_calidad_fuente_fecha');
        $this->index('hallazgos_seguridad', ['severidad', 'estado'], 'idx_hallazgos_severidad_estado');
    }

    public function down(): void
    {
        // Los índices se eliminan solo si existen; no se tocan datos.
        foreach ([
            ['miembros', 'idx_miembros_estado_ingreso'],
            ['miembros', 'idx_miembros_f_nacimiento'],
            ['miembros', 'idx_miembros_sexo'],
            ['ingresos', 'idx_ingresos_fecha'],
            ['ingresos', 'idx_ingresos_culto_fecha'],
            ['ingresos', 'idx_ingresos_actividad'],
            ['ingresos', 'idx_ingresos_tipo'],
            ['ingresos', 'idx_ingresos_categoria'],
            ['egresos', 'idx_egresos_fecha'],
            ['egresos', 'idx_egresos_contrato'],
            ['egresos', 'idx_egresos_lider'],
            ['egresos', 'idx_egresos_tipo'],
            ['egresos', 'idx_egresos_categoria'],
            ['asistencias_sesion', 'uq_asistencias_sesion_miembro'],
            ['asistencias_sesion', 'idx_asistencias_miembro'],
            ['asistencias_sesion', 'idx_asistencias_sesion_estado'],
            ['sesiones', 'idx_sesiones_curso_fecha'],
            ['sesiones', 'idx_sesiones_docente_fecha'],
            ['inscripciones', 'idx_inscripciones_curso_estado'],
            ['inscripciones', 'idx_inscripciones_vencimiento'],
            ['pago_cursos', 'idx_pagos_inscripcion_fecha'],
            ['pago_cursos', 'idx_pagos_fecha'],
            ['actividades', 'idx_actividades_fecha_estado'],
            ['actividades', 'idx_actividades_ministerio_fecha'],
            ['seguimiento_actividades', 'idx_seguimiento_actividad_fecha'],
            ['contratos', 'idx_contratos_miembro_fechas'],
            ['contratos', 'idx_contratos_fin_activo'],
            ['contratos', 'idx_contratos_cargo'],
            ['miembro_ministerio', 'idx_miembro_ministerio_ministerio'],
            ['miembro_ministerio', 'idx_miembro_ministerio_miembro'],
            ['roles', 'idx_roles_nombre'],
            ['users', 'idx_users_last_login'],
            ['audit_logs', 'idx_audit_logs_modulo_fecha'],
            ['audit_logs', 'idx_audit_logs_user_fecha'],
            ['metricas_calidad', 'idx_metricas_calidad_fuente_fecha'],
            ['hallazgos_seguridad', 'idx_hallazgos_severidad_estado'],
        ] as [$tabla, $indice]) {
            if (Schema::hasTable($tabla) && Schema::hasIndex($tabla, $indice)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($indice));
            }
        }
    }

    private function index(string $table, array $columns, string $name): void
    {
        if (! $this->todasLasColumnas($table, $columns)) {
            return;
        }
        if (! Schema::hasIndex($table, $columns)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    private function unique(string $table, array $columns, string $name): void
    {
        if (! $this->todasLasColumnas($table, $columns)) {
            return;
        }
        if (! Schema::hasIndex($table, $columns, 'unique')) {
            Schema::table($table, fn (Blueprint $t) => $t->unique($columns, $name));
        }
    }

    private function todasLasColumnas(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function verificarAsistenciasDuplicadas(): void
    {
        $duplicados = DB::table('asistencias_sesion')
            ->select('sesion_id', 'miembro_id')
            ->whereNull('deleted_at')
            ->groupBy('sesion_id', 'miembro_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(3)
            ->get();

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'Hay asistencias duplicadas. Deduplica antes de crear el UNIQUE: '
                .$duplicados->toJson()
            );
        }
    }
};
