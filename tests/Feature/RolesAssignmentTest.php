<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $objetivo;

    private const EMAIL_OBJETIVO = 'objetivo@proyecto.com';

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['nombre' => 'Administrador', 'estado' => 'Activo', 'nivel_acceso' => 1]);
        Role::create([
            'nombre' => 'Secretario',
            'descripcion' => 'Apoyo administrativo',
            'estado' => 'Activo',
            'nivel_acceso' => 2,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Prueba',
            'email' => 'admin_roles@proyecto.com',
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->objetivo = User::factory()->create([
            'name' => 'Usuario Objetivo',
            'email' => self::EMAIL_OBJETIVO,
        ]);
    }

    public function test_administrador_ve_la_pantalla_asignar_rol(): void
    {
        $this->actingAs($this->admin)
            ->get(route('roles.user'))
            ->assertOk()
            ->assertSee('Asignar Rol')
            ->assertSee('Gestiona los roles asignados a los usuarios del sistema')
            ->assertSee('Usuario Objetivo')
            ->assertSee(self::EMAIL_OBJETIVO)
            ->assertSee('Sin roles');
    }

    public function test_formulario_de_asignacion_muestra_los_roles_reales(): void
    {
        $this->actingAs($this->admin)
            ->get(route('roles.user.edit', $this->objetivo->id))
            ->assertOk()
            ->assertSee('Asignar Roles')
            ->assertSee(self::EMAIL_OBJETIVO)
            ->assertSee('Administrador')
            ->assertSee('Secretario')
            ->assertSee('Apoyo administrativo');
    }

    public function test_marca_los_roles_ya_asignados(): void
    {
        $secretario = Role::where('nombre', 'Secretario')->first();
        $this->objetivo->roles()->attach($secretario->id);

        $html = $this->actingAs($this->admin)
            ->get(route('roles.user.edit', $this->objetivo->id))
            ->assertOk()
            ->getContent();

        // La casilla del rol asignado debe venir marcada.
        $this->assertMatchesRegularExpression(
            '/value="'.$secretario->id.'"[^>]*\schecked/',
            $html
        );
    }

    public function test_asigna_roles_al_usuario(): void
    {
        $secretario = Role::where('nombre', 'Secretario')->first();

        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $this->objetivo->id,
                'roles' => [$secretario->id],
            ])
            ->assertRedirect(route('roles.user'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('role_user', [
            'user_id' => $this->objetivo->id,
            'role_id' => $secretario->id,
        ]);
        $this->assertTrue($this->objetivo->fresh()->hasRole('Secretario'));
    }

    public function test_reemplaza_los_roles_anteriores(): void
    {
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $secretario = Role::where('nombre', 'Secretario')->first();
        $this->objetivo->roles()->attach($adminRole->id);

        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $this->objetivo->id,
                'roles' => [$secretario->id],
            ])
            ->assertRedirect(route('roles.user'));

        $this->assertSoftDeleted('role_user', [
            'user_id' => $this->objetivo->id,
            'role_id' => $adminRole->id,
        ]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $this->objetivo->id,
            'role_id' => $secretario->id,
        ]);
    }

    public function test_valida_usuario_inexistente(): void
    {
        $this->actingAs($this->admin)
            ->from(route('roles.user.edit', $this->objetivo->id))
            ->post(route('roles.user.save'), ['user_id' => 999999, 'roles' => []])
            ->assertSessionHasErrors('user_id');
    }

    public function test_valida_rol_inexistente(): void
    {
        $this->actingAs($this->admin)
            ->from(route('roles.user.edit', $this->objetivo->id))
            ->post(route('roles.user.save'), [
                'user_id' => $this->objetivo->id,
                'roles' => [999999],
            ])
            ->assertSessionHasErrors('roles.0');
    }

    public function test_limpia_los_roles_del_usuario(): void
    {
        $secretario = Role::where('nombre', 'Secretario')->first();
        $this->objetivo->roles()->attach($secretario->id);

        $this->actingAs($this->admin)
            ->post(route('roles.user.clear', $this->objetivo->id))
            ->assertRedirect(route('roles.user'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('role_user', [
            'user_id' => $this->objetivo->id,
            'role_id' => $secretario->id,
        ]);
    }

    public function test_usuario_sin_rol_administrador_no_accede(): void
    {
        $secretario = Role::where('nombre', 'Secretario')->first();
        $usuario = User::factory()->create(['email' => 'sec_roles@proyecto.com']);
        $usuario->roles()->attach($secretario->id);

        $this->actingAs($usuario)->get(route('roles.user'))->assertForbidden();
        $this->actingAs($usuario)
            ->get(route('roles.user.edit', $this->objetivo->id))
            ->assertForbidden();
        $this->actingAs($usuario)
            ->post(route('roles.user.save'), ['user_id' => $this->objetivo->id, 'roles' => []])
            ->assertForbidden();
    }
}
