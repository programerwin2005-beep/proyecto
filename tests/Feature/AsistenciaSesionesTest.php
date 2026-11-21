<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * En el selector de asistencia solo aparecen las sesiones pendientes.
 * Una sesión ya tomada se conserva cuando se abre para modificarla.
 */
class AsistenciaSesionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Curso $curso;

    private Sesion $sesionTomada;

    private Sesion $sesionPendiente;

    private Sesion $sesionFutura;

    private Miembro $miembro;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);

        $this->curso = Curso::create([
            'nombre' => 'Curso de Prueba',
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
        ]);

        $this->sesionTomada = Sesion::create([
            'id_curso' => $this->curso->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00',
            'tema' => 'Sesion Ya Tomada',
        ]);

        $this->sesionPendiente = Sesion::create([
            'id_curso' => $this->curso->id,
            'fecha' => now()->subDay()->toDateString(),
            'hora' => '10:00',
            'tema' => 'Sesion Pendiente',
        ]);

        $this->sesionFutura = Sesion::create([
            'id_curso' => $this->curso->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '11:00',
            'tema' => 'Sesion Futura',
        ]);

        $this->miembro = Miembro::create([
            'nombre' => 'Miembro de Prueba',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        Inscripcion::create([
            'miembro_id' => $this->miembro->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        Asistencia::create([
            'sesion_id' => $this->sesionTomada->id,
            'miembro_id' => $this->miembro->id,
            'estado' => 'Presente',
        ]);
    }

    public function test_ver_sesion_con_asistencia_registrada_carga_el_detalle(): void
    {
        $this->actingAs($this->admin)
            ->get(route('sesiones.show', $this->sesionTomada))
            ->assertOk()
            ->assertSee($this->sesionTomada->tema)
            ->assertSee('Miembro de Prueba')
            ->assertSee('Presente');
    }

    public function test_el_selector_no_muestra_sesiones_que_ya_tienen_asistencia(): void
    {
        $this->actingAs($this->admin)
            ->get(route('asistencia.registrar'))
            ->assertOk()
            ->assertSee($this->sesionPendiente->tema)
            ->assertDontSee($this->sesionTomada->tema);
    }

    public function test_una_sesion_tomada_sigue_disponible_para_modificarla(): void
    {
        $this->actingAs($this->admin)
            ->get(route('asistencia.registrar', ['sesion_id' => $this->sesionTomada->id]))
            ->assertOk()
            ->assertSee($this->sesionTomada->tema)
            ->assertSee('Registrar Asistencia')
            ->assertSee('Miembro de Prueba');
    }

    public function test_el_selector_no_muestra_sesiones_futuras(): void
    {
        $this->actingAs($this->admin)
            ->get(route('asistencia.registrar'))
            ->assertOk()
            ->assertDontSee($this->sesionFutura->tema);
    }

    public function test_no_permite_registrar_asistencia_de_una_sesion_futura(): void
    {
        $this->actingAs($this->admin)
            ->post(route('asistencia.store', $this->sesionFutura), [
                'asistencia' => [
                    $this->miembro->id => ['estado' => 'Presente'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('asistencias_sesion', [
            'sesion_id' => $this->sesionFutura->id,
            'miembro_id' => $this->miembro->id,
        ]);
    }

    public function test_no_permite_marcar_todos_en_una_sesion_futura(): void
    {
        $this->actingAs($this->admin)
            ->post(route('asistencia.marcarTodos', $this->sesionFutura))
            ->assertForbidden();
    }
}
