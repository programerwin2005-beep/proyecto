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
 * Reglas para cursos que ya finalizaron:
 * - No se muestran para inscribir.
 * - No se pueden eliminar sus pagos, ingresos ni inscripciones.
 */
class CursosFinalizadosTest extends TestCase
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

    private function curso(array $datos = []): Curso
    {
        return Curso::create(array_merge([
            'nombre' => 'Curso '.uniqid(),
            'f_inicio' => now()->subMonth()->toDateString(),
            'f_fin' => null,
            'cupo_max' => 10,
        ], $datos));
    }

    private function miembro(string $nombre = 'Miembro de Prueba'): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    private function inscripcion(Curso $curso): Inscripcion
    {
        return Inscripcion::create([
            'miembro_id' => $this->miembro()->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    private function pago(Inscripcion $inscripcion): Pago
    {
        return Pago::registrar($inscripcion, [
            'monto' => 50,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'Efectivo',
        ]);
    }

    public function test_inscripciones_muestra_todos_los_cursos_e_indica_los_cerrados(): void
    {
        $finalizado = $this->curso([
            'nombre' => 'Curso Ya Finalizado',
            'f_fin' => now()->subDay()->toDateString(),
        ]);

        $abierto = $this->curso([
            'nombre' => 'Curso Todavia Abierto',
            'f_fin' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('inscripciones.index'))
            ->assertOk()
            ->assertSee($finalizado->nombre)
            ->assertSee($abierto->nombre)
            ->assertSee('Cerrado')
            ->assertSee('Abierto');
    }

    public function test_no_permite_inscribir_en_un_curso_finalizado(): void
    {
        $curso = $this->curso(['f_fin' => now()->subDay()->toDateString()]);
        $miembro = $this->miembro();

        $this->actingAs($this->admin)
            ->post(route('inscripciones.store', $curso), [
                'miembro_id' => $miembro->id,
                'fecha_inscripcion' => now()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_pagos_ofrece_un_curso_finalizado_mientras_tenga_saldo_pendiente(): void
    {
        $curso = $this->curso([
            'nombre' => 'Curso Finalizado con Deuda',
            'f_fin' => now()->subDay()->toDateString(),
            'tiene_pago' => true,
            'monto_inscripcion' => 50,
        ]);

        $inscripcion = $this->inscripcion($curso);

        $this->actingAs($this->admin)
            ->get(route('pagos.create'))
            ->assertOk()
            ->assertSee($curso->nombre);

        // Al quedar sin saldo pendiente deja de ofrecerse para un nuevo pago.
        $this->pago($inscripcion);

        $this->actingAs($this->admin)
            ->get(route('pagos.create'))
            ->assertOk()
            ->assertDontSee($curso->nombre);
    }

    public function test_permite_registrar_el_pago_pendiente_de_un_curso_finalizado(): void
    {
        $curso = $this->curso([
            'f_fin' => now()->subDay()->toDateString(),
            'tiene_pago' => true,
            'monto_inscripcion' => 50,
        ]);

        $inscripcion = $this->inscripcion($curso);

        $this->actingAs($this->admin)
            ->post(route('pagos.store'), [
                'inscripcion_id' => $inscripcion->id,
                'monto' => 50,
                'fecha_pago' => now()->toDateString(),
                'metodo_pago' => 'Efectivo',
            ])
            ->assertRedirect(route('pagos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pagos', [
            'inscripcion_id' => $inscripcion->id,
            'monto' => 50,
        ]);
    }

    public function test_no_permite_eliminar_el_pago_de_un_curso_finalizado(): void
    {
        $curso = $this->curso(['f_fin' => now()->subDay()->toDateString()]);
        $pago = $this->pago($this->inscripcion($curso));

        $this->actingAs($this->admin)
            ->delete(route('pagos.destroy', $pago->id))
            ->assertRedirect(route('pagos.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('pagos', ['id' => $pago->id]);
        $this->assertNotSoftDeleted('ingresos', ['pago_id' => $pago->id]);
    }

    public function test_no_permite_eliminar_el_ingreso_de_un_pago_de_curso_finalizado(): void
    {
        $curso = $this->curso(['f_fin' => now()->subDay()->toDateString()]);
        $pago = $this->pago($this->inscripcion($curso));
        $ingreso = Ingreso::where('pago_id', $pago->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('ingresos.destroy', $ingreso->id))
            ->assertRedirect(route('ingresos.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('ingresos', ['id' => $ingreso->id]);
    }

    public function test_no_permite_eliminar_la_inscripcion_de_un_curso_finalizado(): void
    {
        $curso = $this->curso(['f_fin' => now()->subDay()->toDateString()]);
        $inscripcion = $this->inscripcion($curso);
        $pago = $this->pago($inscripcion);

        $this->actingAs($this->admin)
            ->delete(route('inscripciones.destroy', $inscripcion->id))
            ->assertRedirect(route('inscripciones.gestion', $curso->id))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('inscripciones', ['id' => $inscripcion->id]);
        $this->assertNotSoftDeleted('pagos', ['id' => $pago->id]);
    }

    public function test_si_el_curso_no_ha_finalizado_el_pago_se_puede_eliminar(): void
    {
        $curso = $this->curso(['f_fin' => now()->addDay()->toDateString()]);
        $pago = $this->pago($this->inscripcion($curso));

        $this->actingAs($this->admin)
            ->delete(route('pagos.destroy', $pago->id))
            ->assertRedirect(route('pagos.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('pagos', ['id' => $pago->id]);
        $this->assertSoftDeleted('ingresos', ['pago_id' => $pago->id]);
    }
}
