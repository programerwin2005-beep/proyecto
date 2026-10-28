<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermisosPorRolSeeder extends Seeder
{
    /**
     * Módulos habilitados por rol. 'all' concede acceso total.
     */
    public const DEFINICIONES = [
        'Administrador' => 'all',
        'Secretario' => [
            'cargos', 'lideres', 'miembros', 'ministerios', 'actividades',
            'seguimiento', 'cursos', 'docentes', 'inscripciones', 'sesiones', 'asistencia',
        ],
        'Tesorero' => ['cultos', 'ingresos', 'egresos', 'contratos'],
        // El docente solo consulta sus sesiones y registra asistencia.
        'Docente' => [
            'sesiones' => ['ver'],
            'asistencia' => ['ver', 'crear', 'buscar'],
            'reportes.asistencia' => ['ver'],
        ],
    ];

    /**
     * Acciones que se evalúan para cada módulo.
     */
    public const ACCIONES = ['ver', 'crear', 'editar', 'eliminar', 'buscar'];

    /**
     * Aplica la matriz a todos los roles existentes.
     */
    public function run(): void
    {
        foreach (Role::withTrashed()->get() as $role) {
            $this->sembrarPara($role);
        }
    }

    /**
     * Crea o actualiza la fila de permisos de un rol para todos los módulos.
     */
    public function sembrarPara(Role $role): void
    {
        $modulos = self::modulos();
        $tipo = self::DEFINICIONES[$role->nombre] ?? [];
        $ahora = now();

        foreach ($modulos as $modulo => $accionesModulo) {
            $accionesPermitidas = $this->accionesPermitidas($tipo, $modulo, $accionesModulo);

            $valores = [];
            foreach (self::ACCIONES as $accion) {
                $valores[$accion] = in_array($accion, $accionesPermitidas, true);
            }

            DB::table('permisos')->updateOrInsert(
                ['role_id' => $role->id, 'modulo' => $modulo],
                $valores + ['updated_at' => $ahora, 'created_at' => $ahora]
            );
        }
    }

    /**
     * Resuelve las acciones concedidas según el tipo de definición:
     *  - 'all': todas las acciones de todos los módulos.
     *  - lista de módulos: todas las acciones de esos módulos (y de reportes).
     *  - mapa módulo => acciones: solo las acciones indicadas.
     *
     * @param  string|array<int, string>|array<string, array<int, string>>  $tipo
     * @param  array<int, string>  $accionesModulo
     * @return array<int, string>
     */
    private function accionesPermitidas(string|array $tipo, string $modulo, array $accionesModulo): array
    {
        if ($tipo === 'all') {
            return $accionesModulo;
        }

        if ($tipo === []) {
            return [];
        }

        if (array_is_list($tipo)) {
            $permitido = in_array($modulo, $tipo, true) || str_starts_with($modulo, 'reportes');

            return $permitido ? $accionesModulo : [];
        }

        return array_values(array_intersect($tipo[$modulo] ?? [], $accionesModulo));
    }

    /**
     * Módulos declarados en config/permisos.php con sus acciones válidas.
     *
     * @return array<string, array<int, string>>
     */
    public static function modulos(): array
    {
        $modulos = [];

        foreach (config('permisos.categorias', []) as $grupo) {
            foreach ($grupo as $modulo => $meta) {
                $modulos[$modulo] = $meta['acciones'] ?? [];
            }
        }

        return $modulos;
    }
}
