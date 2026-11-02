<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * G1, G2 y G4: último acceso, usuarios inactivos y módulos con más actividad.
 */
class SeguridadKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_login_registra_el_ultimo_acceso(): void
    {
        $user = User::factory()->create([
            'email' => 'acceso@proyecto.com',
            'password' => 'clave-segura',
        ]);

        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'email' => 'acceso@proyecto.com',
            'password' => 'clave-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_seguridad_cuenta_usuarios_inactivos_y_modulos_del_periodo(): void
    {
        User::factory()->create(['last_login_at' => now()]);
        User::factory()->create(['last_login_at' => now()->subDays(45)]);
        User::factory()->create(['last_login_at' => null]);

        DB::table('audit_logs')->insert([
            ['modulo' => 'miembros', 'accion' => 'crear', 'created_at' => now(), 'updated_at' => now()],
            ['modulo' => 'miembros', 'accion' => 'editar', 'created_at' => now(), 'updated_at' => now()],
            ['modulo' => 'miembros', 'accion' => 'eliminar', 'created_at' => now(), 'updated_at' => now()],
            ['modulo' => 'egresos', 'accion' => 'crear', 'created_at' => now(), 'updated_at' => now()],
            ['modulo' => 'antiguo', 'accion' => 'crear', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()],
        ]);

        $kpis = (new KpiService)->seguridad(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        );

        $this->assertSame(3, $kpis['total_usuarios']);
        $this->assertSame(2, $kpis['inactivos_30']);
        $this->assertCount(2, $kpis['inactivos_lista']);
        $this->assertSame('miembros', $kpis['modulos'][0]['modulo']);
        $this->assertSame(3, $kpis['modulos'][0]['total']);
        $this->assertSame('egresos', $kpis['modulos'][1]['modulo']);
        $this->assertNotContains('antiguo', array_column($kpis['modulos'], 'modulo'));
    }

    public function test_seguridad_cuenta_usuarios_sin_rol_sin_duplicar_pivotes_borrados(): void
    {
        $role = Role::create(['nombre' => 'Tesorero', 'estado' => 'Activo', 'nivel_acceso' => 3]);

        $conRol = User::factory()->create(['last_login_at' => now()]);
        $conRol->roles()->attach($role->id);

        $sinRol = User::factory()->create(['last_login_at' => now()]);
        $sinRol->roles()->attach($role->id);
        $sinRol->roles()->detach($role->id);

        $kpis = (new KpiService)->seguridad();

        $this->assertSame(1, $kpis['sin_rol']);
        $this->assertSame($sinRol->email, $kpis['sin_rol_lista'][0]['email']);

        $rolTesorero = collect($kpis['roles'])->firstWhere('id', $role->id);

        $this->assertNotNull($rolTesorero);
        $this->assertSame(1, $rolTesorero['usuarios']);
    }
}
