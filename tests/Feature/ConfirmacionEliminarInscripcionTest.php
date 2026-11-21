<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Al eliminar una inscripción se pide confirmación y, si tiene pagos,
 * el mensaje advierte que también se eliminarán sus pagos e ingresos.
 */
class ConfirmacionEliminarInscripcionTest extends TestCase
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

    private function inscripcion(): Inscripcion
    {
        $miembro = Miembro::create([
            'nombre' => 'Miembro de Prueba',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $curso = Curso::create([
            'nombre' => 'Curso de Prueba',
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
            'tiene_pago' => true,
            'monto_inscripcion' => 50,
        ]);

        return Inscripcion::create([
            'miembro_id' => $miembro->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    public function test_pide_confirmacion_simple_cuando_la_inscripcion_no_tiene_pagos(): void
    {
        $inscripcion = $this->inscripcion();

        $this->actingAs($this->admin)
            ->get(route('inscripciones.gestion', $inscripcion->curso_id))
            ->assertOk()
            ->assertSee('¿Está seguro de eliminar esta inscripción?')
            ->assertDontSee('Esta inscripción tiene pagos');
    }

    public function test_advierte_que_tiene_pagos_al_confirmar_la_eliminacion(): void
    {
        $inscripcion = $this->inscripcion();

        Pago::registrar($inscripcion, [
            'monto' => 50,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'Efectivo',
        ]);

        $this->actingAs($this->admin)
            ->get(route('inscripciones.gestion', $inscripcion->curso_id))
            ->assertOk()
            ->assertSee('Esta inscripción tiene pagos')
            ->assertSee('También se eliminarán sus pagos e ingresos');
    }
}
