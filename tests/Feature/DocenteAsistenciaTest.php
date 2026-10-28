<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceso del docente: solo ve sus cursos/sesiones y registra su asistencia.
 */
class DocenteAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $docenteUser;

    private Docente $docente;

    private Curso $curso;

    private Sesion $sesion;

    private Miembro $alumno;

    private Curso $cursoAjeno;

    private Sesion $sesionAjena;

    private Miembro $alumnoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'admin_docente@test.com']);
        $this->admin->roles()->attach($this->rol('Administrador')->id);

        [$this->docente, $this->docenteUser] = $this->crearDocente('Docente Propio', 'docente_propio@test.com');
        [$docenteAjeno] = $this->crearDocente('Docente Ajeno', 'docente_ajeno@test.com');

        $this->curso = $this->crearCurso('Curso Propio');
        $this->curso->docentes()->attach($this->docente->id);
        $this->sesion = $this->crearSesion($this->curso, $this->docente, '2026-10-20');

        $this->alumno = $this->crearMiembro('Alumno Propio', 'alumno_propio@test.com');
        Inscripcion::create([
            'miembro_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->cursoAjeno = $this->crearCurso('Curso Ajeno');
        $this->cursoAjeno->docentes()->attach($docenteAjeno->id);
        $this->sesionAjena = $this->crearSesion($this->cursoAjeno, $docenteAjeno, '2026-10-21');

        $this->alumnoAjeno = $this->crearMiembro('Alumno Ajeno', 'alumno_ajeno@test.com');
        Inscripcion::create([
            'miembro_id' => $this->alumnoAjeno->id,
            'curso_id' => $this->cursoAjeno->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }

    public function test_docente_solo_ve_sus_sesiones(): void
    {
        $this->actingAs($this->docenteUser)
            ->get(route('sesiones.index'))
            ->assertOk()
            ->assertSee('Curso Propio')
            ->assertDontSee('Curso Ajeno');
    }

    public function test_docente_ve_solo_sus_cursos_al_registrar_asistencia(): void
    {
        $this->actingAs($this->docenteUser)
            ->get(route('asistencia.registrar'))
            ->assertOk()
            ->assertSee('Curso Propio')
            ->assertDontSee('Curso Ajeno');
    }

    public function test_docente_puede_tomar_asistencia_y_se_registra_el_usuario(): void
    {
        $this->actingAs($this->docenteUser)
            ->post(route('asistencia.store', $this->sesion), [
                'asistencia' => [
                    $this->alumno->id => ['estado' => 'Presente', 'observacion' => ''],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('asistencias_sesion', [
            'sesion_id' => $this->sesion->id,
            'miembro_id' => $this->alumno->id,
            'estado' => 'Presente',
            'user_id' => $this->docenteUser->id,
        ]);
    }

    public function test_docente_no_puede_tomar_asistencia_de_una_sesion_ajena(): void
    {
        $this->actingAs($this->docenteUser)
            ->post(route('asistencia.store', $this->sesionAjena), [
                'asistencia' => [
                    $this->alumnoAjeno->id => ['estado' => 'Presente'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('asistencias_sesion', [
            'sesion_id' => $this->sesionAjena->id,
            'miembro_id' => $this->alumnoAjeno->id,
        ]);
    }

    public function test_docente_no_puede_ver_una_sesion_ajena_por_url(): void
    {
        $this->actingAs($this->docenteUser)
            ->get(route('sesiones.show', $this->sesionAjena))
            ->assertForbidden();
    }

    public function test_docente_no_puede_ver_el_historial_de_un_miembro_ajeno(): void
    {
        $this->actingAs($this->docenteUser)
            ->get(route('asistencia.historial', $this->alumnoAjeno->id))
            ->assertForbidden();
    }

    public function test_docente_no_accede_a_modulos_administrativos(): void
    {
        $this->actingAs($this->docenteUser)->get(route('miembros.index'))->assertForbidden();
        $this->actingAs($this->docenteUser)->get(route('ingresos.index'))->assertForbidden();
        $this->actingAs($this->docenteUser)->get(route('users.index'))->assertForbidden();
    }

    public function test_docente_ve_sus_cursos_y_sesiones_asignados_directamente(): void
    {
        $cursoDirecto = $this->crearCurso('Curso Directo');
        $this->crearSesion($cursoDirecto, $this->docente, '2026-10-22');

        $this->actingAs($this->docenteUser)
            ->get(route('sesiones.index'))
            ->assertOk()
            ->assertSee('Curso Directo');

        $this->actingAs($this->docenteUser)
            ->get(route('asistencia.registrar'))
            ->assertOk()
            ->assertSee('Curso Directo');
    }

    public function test_docente_inactivo_no_puede_tomar_asistencia(): void
    {
        $this->docente->update(['activo' => false]);

        $this->actingAs($this->docenteUser)
            ->post(route('asistencia.store', $this->sesion), [
                'asistencia' => [
                    $this->alumno->id => ['estado' => 'Presente'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('asistencias_sesion', [
            'sesion_id' => $this->sesion->id,
            'miembro_id' => $this->alumno->id,
        ]);
    }

    public function test_docente_solo_ve_sus_cursos_en_el_reporte_de_asistencia(): void
    {
        $this->actingAs($this->docenteUser)
            ->get(route('reportes.asistencia'))
            ->assertOk()
            ->assertSee('Curso Propio')
            ->assertDontSee('Curso Ajeno');
    }

    public function test_administrador_crea_el_acceso_de_un_docente(): void
    {
        $miembro = $this->crearMiembro('Docente Nuevo', 'docente_nuevo@test.com');
        $docente = Docente::create([
            'id_miembro' => $miembro->id,
            'especialidad' => 'Teología',
            'activo' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('docentes.acceso.store', $docente), [
                'email' => 'acceso_docente@test.com',
                'password' => 'clave-segura-123',
                'password_confirmation' => 'clave-segura-123',
            ])
            ->assertRedirect(route('docentes.show', $docente));

        $user = User::where('email', 'acceso_docente@test.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Docente'));
        $this->assertDatabaseHas('docentes', [
            'id' => $docente->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_administrador_ve_el_estado_de_acceso_del_docente(): void
    {
        $this->actingAs($this->admin)
            ->get(route('docentes.index'))
            ->assertOk()
            ->assertSee('Con acceso');

        $this->actingAs($this->admin)
            ->get(route('docentes.show', $this->docente))
            ->assertOk()
            ->assertSee($this->docenteUser->email);
    }

    public function test_eliminar_un_docente_retira_su_acceso(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('docentes.destroy', $this->docente))
            ->assertRedirect(route('docentes.index'));

        $this->assertSoftDeleted('docentes', ['id' => $this->docente->id]);
        $this->assertSoftDeleted('users', ['id' => $this->docenteUser->id]);

        $user = User::withTrashed()->find($this->docenteUser->id);

        $this->assertNotNull($user);
        $this->assertFalse($user->roles()->where('nombre', 'Docente')->exists());
    }

    private function rol(string $nombre): Role
    {
        return Role::firstOrCreate(
            ['nombre' => $nombre],
            [
                'descripcion' => $nombre,
                'estado' => 'Activo',
                'nivel_acceso' => 1,
                'guard_name' => 'web',
            ]
        );
    }

    /**
     * @return array{0: Docente, 1: User}
     */
    private function crearDocente(string $nombre, string $email): array
    {
        $miembro = Miembro::create([
            'nombre' => $nombre,
            'email' => $email,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $docente = Docente::create([
            'id_miembro' => $miembro->id,
            'especialidad' => 'Teología',
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => $nombre,
            'email' => $email,
        ]);

        $user->roles()->attach($this->rol('Docente')->id);
        $docente->update(['user_id' => $user->id]);

        return [$docente, $user];
    }

    private function crearCurso(string $nombre): Curso
    {
        return Curso::create([
            'nombre' => $nombre,
            'f_inicio' => '2026-10-01',
            'f_fin' => '2026-11-30',
            'cupo_max' => 10,
            'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'tiene_pago' => false,
            'monto_inscripcion' => 0,
        ]);
    }

    private function crearSesion(Curso $curso, Docente $docente, string $fecha): Sesion
    {
        return Sesion::create([
            'id_curso' => $curso->id,
            'id_docente' => $docente->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'tema' => 'Sesión de prueba',
        ]);
    }

    private function crearMiembro(string $nombre, string $email): Miembro
    {
        return Miembro::create([
            'nombre' => $nombre,
            'email' => $email,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);
    }
}
