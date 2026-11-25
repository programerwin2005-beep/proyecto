<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisosAccionesTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $nombre): array
    {
        $rol = Role::firstOrCreate(['nombre' => $nombre], ['estado' => 'Activo', 'nivel_acceso' => 1]);
        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol);

        return [$usuario, $rol];
    }

    public function test_admin_configura_acciones_especificas_y_desmarcarlas_revoca_el_acceso(): void
    {
        [$admin] = $this->usuarioConRol('Administrador');
        [$usuario, $rol] = $this->usuarioConRol('Operador');

        $this->actingAs($admin)->get(route('permisos.edit', $rol))
            ->assertOk()->assertSee('Pagar contrato')->assertSee('Completar tarea');

        $this->put(route('permisos.update', $rol), ['permisos' => [
            'egresos' => ['ver' => 1, 'pagar_contrato' => 1, 'asignar_roles' => 1],
            'reportes.miembros' => ['ver' => 1],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue($usuario->puede('egresos', 'pagar_contrato'));
        $this->assertFalse($usuario->puede('egresos', 'asignar_roles'));
        $this->assertFalse($usuario->puede('egresos', 'crear'));
        $this->assertTrue($usuario->puede('reportes.miembros', 'ver'));

        $this->put(route('permisos.update', $rol), ['permisos' => [
            'egresos' => ['ver' => 1],
        ]])->assertSessionHasNoErrors();
        $usuario->limpiarPermisos();
        $this->assertFalse($usuario->puede('egresos', 'pagar_contrato'));
    }

    public function test_pagar_contrato_es_independiente_de_crear_egresos(): void
    {
        [$usuario, $rol] = $this->usuarioConRol('Operador');
        $permiso = $rol->permisos()->create(['modulo' => 'egresos', 'ver' => true, 'crear' => true]);

        $this->actingAs($usuario)->get(route('egresos.pagar-contrato'))->assertForbidden();
        $this->post(route('egresos.pagar-contrato.store'), [])->assertForbidden();

        $permiso->update(['crear' => false, 'pagar_contrato' => true]);
        $usuario->limpiarPermisos();
        $this->get(route('egresos.pagar-contrato'))->assertOk();
        $this->get(route('egresos.create'))->assertForbidden();
        $this->get(route('egresos.index'))->assertOk()->assertSee('Pagar Contrato')->assertDontSee('+ Nuevo Egreso');
    }

    public function test_ingreso_de_actividad_ya_no_queda_sin_control_de_permisos(): void
    {
        [$usuario, $rol] = $this->usuarioConRol('Operador');
        $permiso = $rol->permisos()->create(['modulo' => 'ingresos', 'ver' => true, 'crear' => true]);
        $this->actingAs($usuario)->get(route('ingresos.actividad.create'))->assertForbidden();
        $this->post(route('ingresos.actividad.store'), [])->assertForbidden();

        $permiso->update(['ingreso_actividad' => true]);
        $usuario->limpiarPermisos();
        $this->get(route('ingresos.actividad.create'))->assertOk();
    }
}
