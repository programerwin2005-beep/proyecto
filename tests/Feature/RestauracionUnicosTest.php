<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Ingreso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\PagoCurso;
use App\Models\Role;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recrear una relación cuyo UNIQUE incluye la fila eliminada debe restaurar
 * esa fila, sin errores de base de datos y sin duplicados.
 */
class RestauracionUnicosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Miembro $miembro;

    private Curso $curso;

    private Sesion $sesion;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($rol->id);

        $this->miembro = $this->crearMiembro('Miembro Inscrito');
        $this->curso = $this->crearCurso('Curso Único');

        Inscripcion::create([
            'miembro_id' => $this->miembro->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->subWeek()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->sesion = Sesion::create([
            'id_curso' => $this->curso->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'tema' => 'Sesión de prueba',
        ]);
    }

    public function test_recrear_asistencia_eliminada_restaura_la_fila(): void
    {
        $this->actingAs($this->admin)
            ->post(route('asistencia.store', $this->sesion), [
                'asistencia' => [
                    $this->miembro->id => ['estado' => 'Presente', 'observacion' => ''],
                ],
            ])
            ->assertRedirect();

        $asistencia = Asistencia::firstOrFail();
        $asistencia->delete();

        $this->actingAs($this->admin)
            ->post(route('asistencia.store', $this->sesion), [
                'asistencia' => [
                    $this->miembro->id => ['estado' => 'Tardanza', 'observacion' => 'Llegó tarde'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Asistencia::withTrashed()->count());
        $this->assertNotSoftDeleted('asistencias_sesion', ['id' => $asistencia->id]);
        $this->assertDatabaseHas('asistencias_sesion', [
            'id' => $asistencia->id,
            'estado' => 'Tardanza',
        ]);
    }

    public function test_reinscribir_restaura_la_inscripcion_eliminada(): void
    {
        $miembro = $this->crearMiembro('Miembro Nuevo');

        $this->actingAs($this->admin)
            ->post(route('inscripciones.store', $this->curso), [
                'miembro_id' => $miembro->id,
                'fecha_inscripcion' => now()->toDateString(),
            ])
            ->assertRedirect();

        $inscripcion = Inscripcion::where('miembro_id', $miembro->id)->firstOrFail();
        $inscripcion->delete();

        $this->actingAs($this->admin)
            ->post(route('inscripciones.store', $this->curso), [
                'miembro_id' => $miembro->id,
                'fecha_inscripcion' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Inscripción reactivada con éxito.');

        $this->assertSame(
            1,
            Inscripcion::withTrashed()->where('miembro_id', $miembro->id)->count()
        );
        $this->assertNotSoftDeleted('inscripciones', ['id' => $inscripcion->id]);
    }

    public function test_registrar_docente_eliminado_restaura_el_registro(): void
    {
        $miembro = $this->crearMiembro('Miembro Docente');

        $this->actingAs($this->admin)
            ->post(route('docentes.store'), [
                'id_miembro' => $miembro->id,
                'especialidad' => 'Matemática',
                'activo' => 1,
            ])
            ->assertRedirect();

        $docente = Docente::where('id_miembro', $miembro->id)->firstOrFail();
        $docente->delete();

        $this->actingAs($this->admin)
            ->post(route('docentes.store'), [
                'id_miembro' => $miembro->id,
                'especialidad' => 'Física',
                'activo' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Docente reactivado con éxito.');

        $this->assertSame(1, Docente::withTrashed()->where('id_miembro', $miembro->id)->count());
        $this->assertNotSoftDeleted('docentes', ['id' => $docente->id]);
        $this->assertDatabaseHas('docentes', [
            'id' => $docente->id,
            'especialidad' => 'Física',
        ]);
    }

    public function test_reactivar_rol_eliminado_restaura_el_pivote(): void
    {
        $usuario = User::factory()->create();
        $rol = Role::where('nombre', 'Administrador')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $usuario->id,
                'roles' => [$rol->id],
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $usuario->id,
                'roles' => [],
            ])
            ->assertRedirect();

        $this->assertFalse($usuario->fresh()->hasRole('Administrador'));
        $this->assertSoftDeleted('role_user', [
            'user_id' => $usuario->id,
            'role_id' => $rol->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('roles.user.save'), [
                'user_id' => $usuario->id,
                'roles' => [$rol->id],
            ])
            ->assertRedirect();

        $this->assertTrue($usuario->fresh()->hasRole('Administrador'));
        $this->assertSame(1, DB::table('role_user')->where('user_id', $usuario->id)->count());
        $this->assertDatabaseHas('role_user', [
            'user_id' => $usuario->id,
            'role_id' => $rol->id,
            'deleted_at' => null,
        ]);
    }

    public function test_recrear_ingreso_de_pago_eliminado_restaura_la_fila(): void
    {
        $inscripcion = Inscripcion::where('curso_id', $this->curso->id)->firstOrFail();

        $pago = PagoCurso::create([
            'inscripcion_id' => $inscripcion->id,
            'monto' => 50,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'Efectivo',
            'registrado_por' => $this->admin->id,
        ]);

        $ingreso = Ingreso::crearORestaurar(
            ['pago_curso_id' => $pago->id],
            ['tipo' => 'Inscripción Curso: '.$this->curso->nombre]
        );

        $ingreso->delete();

        $restaurado = Ingreso::crearORestaurar(
            ['pago_curso_id' => $pago->id],
            ['tipo' => 'Inscripción Curso: '.$this->curso->nombre]
        );

        $this->assertSame($ingreso->id, $restaurado->id);
        $this->assertSame(
            1,
            Ingreso::withTrashed()->where('pago_curso_id', $pago->id)->count()
        );
        $this->assertNotSoftDeleted('ingresos', ['id' => $ingreso->id]);
    }

    private function crearMiembro(string $nombre): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    private function crearCurso(string $nombre): Curso
    {
        return Curso::create([
            'nombre' => $nombre,
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
        ]);
    }
}
