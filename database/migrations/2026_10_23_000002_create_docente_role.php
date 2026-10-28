<?php

use App\Models\Role;
use Database\Seeders\PermisosPorRolSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea el rol Docente con permisos mínimos: ver sus sesiones y registrar
     * la asistencia de sus cursos.
     */
    public function up(): void
    {
        $role = Role::withTrashed()->where('nombre', 'Docente')->first();

        if (! $role) {
            $role = Role::create([
                'nombre' => 'Docente',
                'descripcion' => 'Registra la asistencia de los cursos que tiene asignados.',
                'estado' => 'Activo',
                'nivel_acceso' => 4,
                'guard_name' => 'web',
            ]);
        } elseif ($role->trashed()) {
            $role->restore();
        }

        (new PermisosPorRolSeeder)->sembrarPara($role);
    }

    public function down(): void
    {
        $role = Role::withTrashed()->where('nombre', 'Docente')->first();

        if (! $role) {
            return;
        }

        DB::table('role_user')->where('role_id', $role->id)->delete();
        $role->permisos()->delete();
        $role->forceDelete();
    }
};
