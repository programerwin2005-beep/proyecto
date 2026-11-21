<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Ingreso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los ingresos generados por pagos de inscripción solo se eliminan desde
 * el módulo de Pagos; en Ingresos no se ofrece ni se permite la eliminación.
 */
class IngresosPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);
    }

    private function pagoDeInscripcion(): Pago
    {
        $miembro = Miembro::create([
            'nombre' => 'Miembro de Prueba',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $curso = Curso::create([
            'nombre' => 'Curso de Prueba',
            'f_inicio' => now()->toDateString(),
            'f_fin' => now()->addMonth()->toDateString(),
            'cupo_max' => 10,
            'tiene_pago' => true,
            'monto_inscripcion' => 50,
        ]);

        $inscripcion = Inscripcion::create([
            'miembro_id' => $miembro->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        return Pago::registrar($inscripcion, [
            'monto' => 50,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'Efectivo',
        ]);
    }

    public function test_ingresos_no_muestra_el_boton_eliminar_para_pagos_de_inscripcion(): void
    {
        $pago = $this->pagoDeInscripcion();
        $ingreso = Ingreso::where('pago_id', $pago->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('ingresos.index'))
            ->assertOk()
            ->assertSee('Bs 50.00')
            ->assertDontSee('action="'.route('ingresos.destroy', $ingreso->id).'"', false);
    }

    public function test_ingresos_rechaza_eliminar_un_pago_de_inscripcion(): void
    {
        $pago = $this->pagoDeInscripcion();
        $ingreso = Ingreso::where('pago_id', $pago->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('ingresos.destroy', $ingreso->id))
            ->assertRedirect(route('ingresos.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('ingresos', ['id' => $ingreso->id]);
        $this->assertNotSoftDeleted('pagos', ['id' => $pago->id]);
    }

    public function test_un_ingreso_normal_conserva_su_boton_eliminar(): void
    {
        $ingreso = Ingreso::create([
            'monto_total' => 100,
            'fecha' => now()->toDateString(),
            'tipo' => 'Diezmo',
            'metodo_pago' => 'Efectivo',
        ]);

        $this->actingAs($this->admin)
            ->get(route('ingresos.index'))
            ->assertOk()
            ->assertSee('action="'.route('ingresos.destroy', $ingreso->id).'"', false);
    }
}
