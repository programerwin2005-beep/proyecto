<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const EMAIL_OBJETIVO = 'usuario.filtrado@proyecto.com';

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create([
            'name' => 'Admin Buscador',
            'email' => 'admin.buscador@proyecto.com',
        ]);
        $this->admin->roles()->attach($rol->id);

        User::factory()->create([
            'name' => 'Usuario Filtrado',
            'email' => self::EMAIL_OBJETIVO,
        ]);
    }

    public function test_administrar_usuario_muestra_la_barra_de_busqueda(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Buscar usuario')
            ->assertSee('name="q"', false);
    }

    public function test_busqueda_de_usuario_por_nombre_filtra_la_lista(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index', ['q' => 'Usuario Filtrado']))
            ->assertOk()
            ->assertSee('Usuario Filtrado')
            ->assertDontSee('admin.buscador@proyecto.com');
    }

    public function test_busqueda_de_usuario_por_correo_filtra_la_lista(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index', ['q' => self::EMAIL_OBJETIVO]))
            ->assertOk()
            ->assertSee(self::EMAIL_OBJETIVO)
            ->assertDontSee('admin.buscador@proyecto.com');
    }
}
