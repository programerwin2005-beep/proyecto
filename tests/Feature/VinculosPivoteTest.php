<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\Sesion;
use App\Models\User;
use App\Support\VinculoPivote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los vínculos con `deleted_at` no se borran físicamente: se desactivan y las
 * relaciones Eloquent dejan de devolverlos hasta que se restauran.
 */
class VinculosPivoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_rol_desvinculado_no_se_devuelve_y_se_reactiva(): void
    {
        $usuario = User::factory()->create();
        $rol = Role::create(['nombre' => 'Secretario', 'estado' => 'Activo', 'nivel_acceso' => 2]);

        VinculoPivote::adjuntar($usuario->roles(), $rol->id);
        $this->assertTrue($usuario->fresh()->hasRole('Secretario'));

        VinculoPivote::desvincular($usuario->roles(), $rol->id);
        $usuario->refresh();

        $this->assertFalse($usuario->hasRole('Secretario'));
        $this->assertCount(0, $usuario->roles);
        $this->assertSoftDeleted('role_user', [
            'user_id' => $usuario->id,
            'role_id' => $rol->id,
        ]);

        VinculoPivote::adjuntar($usuario->roles(), $rol->id);

        $this->assertTrue($usuario->fresh()->hasRole('Secretario'));
        $this->assertSame(1, DB::table('role_user')->where('user_id', $usuario->id)->count());
    }

    public function test_miembro_ministerio_desvinculado_no_se_devuelve_y_se_reactiva(): void
    {
        $miembro = $this->crearMiembro('Miembro de Ministerio');
        $ministerio = Ministerio::create(['nombre' => 'Alabanza']);
        $datos = [
            'fecha_inicio' => now()->toDateString(),
        ];

        VinculoPivote::adjuntar($ministerio->miembros(), $miembro->id, $datos);

        $this->assertCount(1, $ministerio->fresh()->miembros);
        $this->assertCount(1, $miembro->fresh()->ministerios);

        VinculoPivote::desvincular($ministerio->miembros(), $miembro->id);

        $this->assertCount(0, $ministerio->fresh()->miembros);
        $this->assertCount(0, $miembro->fresh()->ministerios);
        $this->assertSoftDeleted('miembro_ministerio', [
            'miembro_id' => $miembro->id,
            'ministerio_id' => $ministerio->id,
        ]);

        VinculoPivote::adjuntar($ministerio->miembros(), $miembro->id, $datos);

        $this->assertCount(1, $ministerio->fresh()->miembros);
        $this->assertSame(
            1,
            DB::table('miembro_ministerio')->where('miembro_id', $miembro->id)->count()
        );
    }

    public function test_sincronizar_desactiva_y_reactiva_sin_filas_huerfanas(): void
    {
        $usuario = User::factory()->create();
        $administrador = Role::create(['nombre' => 'Administrador', 'estado' => 'Activo', 'nivel_acceso' => 1]);
        $secretario = Role::create(['nombre' => 'Secretario', 'estado' => 'Activo', 'nivel_acceso' => 2]);

        VinculoPivote::sincronizar($usuario->roles(), [$administrador->id, $secretario->id]);
        $this->assertCount(2, $usuario->fresh()->roles);

        VinculoPivote::sincronizar($usuario->roles(), [$secretario->id]);

        $usuario->refresh();
        $this->assertFalse($usuario->hasRole('Administrador'));
        $this->assertTrue($usuario->hasRole('Secretario'));
        $this->assertSoftDeleted('role_user', [
            'user_id' => $usuario->id,
            'role_id' => $administrador->id,
        ]);
        $this->assertSame(2, DB::table('role_user')->where('user_id', $usuario->id)->count());
        $this->assertSame(
            1,
            DB::table('role_user')
                ->where('user_id', $usuario->id)
                ->whereNull('deleted_at')
                ->count()
        );

        VinculoPivote::sincronizar($usuario->roles(), [$administrador->id]);

        $usuario->refresh();
        $this->assertTrue($usuario->hasRole('Administrador'));
        $this->assertFalse($usuario->hasRole('Secretario'));
        $this->assertSame(2, DB::table('role_user')->where('user_id', $usuario->id)->count());
    }

    public function test_relaciones_de_lectura_ignoran_vinculos_eliminados(): void
    {
        $miembro = $this->crearMiembro('Miembro con Historial');
        $curso = $this->crearCurso('Curso con Historial');

        $inscripcion = Inscripcion::create([
            'miembro_id' => $miembro->id,
            'curso_id' => $curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $sesion = Sesion::create([
            'id_curso' => $curso->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00',
            'tema' => 'Sesión con Historial',
        ]);

        $asistencia = Asistencia::create([
            'miembro_id' => $miembro->id,
            'sesion_id' => $sesion->id,
            'estado' => 'Presente',
        ]);

        $this->assertCount(1, $miembro->fresh()->cursos);
        $this->assertCount(1, $miembro->fresh()->sesiones);

        $inscripcion->delete();
        $asistencia->delete();

        $this->assertCount(0, $miembro->fresh()->cursos);
        $this->assertCount(0, $miembro->fresh()->sesiones);
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
