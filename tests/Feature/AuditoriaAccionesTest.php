<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * G4: auditoría de acciones de creación, edición y eliminación por módulo.
 */
class AuditoriaAccionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $objetivo;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['nombre' => 'Administrador', 'estado' => 'Activo', 'nivel_acceso' => 1]);

        $this->admin = User::factory()->create(['name' => 'Admin Auditoría']);
        $this->admin->roles()->attach($role->id);

        $this->objetivo = User::factory()->create();
    }

    public function test_registra_acciones_de_escritura_con_modulo_y_usuario(): void
    {
        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $this->objetivo->id,
                'roles' => [],
                'password' => 'no-debe-guardarse',
            ])
            ->assertRedirect(route('roles.user'));

        $registro = DB::table('audit_logs')->first();

        $this->assertNotNull($registro);
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame('roles', $registro->modulo);
        $this->assertSame('crear', $registro->accion);
        $this->assertSame('roles.user.save', $registro->entidad_type);
        $this->assertNotNull($registro->ip);
        $this->assertStringNotContainsString('no-debe-guardarse', (string) $registro->metadata);

        $metadata = json_decode($registro->metadata, true);

        $this->assertSame('POST', $metadata['metodo']);
        $this->assertArrayNotHasKey('password', $metadata);
    }

    public function test_no_registra_lecturas_ni_logout(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
