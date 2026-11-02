<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KpiServiceTest extends TestCase
{
    use RefreshDatabase;

    private KpiService $kpis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kpis = new KpiService;
    }

    private function crearMiembro(string $nombre, string $estado, string $fechaIngreso): int
    {
        return DB::table('miembros')->insertGetId([
            'nombre' => $nombre,
            'fecha_ingreso' => $fechaIngreso,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCurso(string $nombre, int $cupo, float $monto): int
    {
        return DB::table('cursos')->insertGetId([
            'nombre' => $nombre,
            'f_inicio' => now()->subMonthNoOverflow()->toDateString(),
            'f_fin' => null,
            'cupo_max' => $cupo,
            'tiene_pago' => true,
            'monto_inscripcion' => $monto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Escenario mínimo con datos de las áreas A a F y G.
     */
    private function sembrarDatos(): array
    {
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $finMes = now()->endOfMonth()->toDateString();

        // A. Membresía: 2 activos y 1 inactivo; una alta en el mes.
        $activo1 = $this->crearMiembro('Ana Activa', 'Activo', $hoy);
        $activo2 = $this->crearMiembro('Beto Activo', 'Activo', now()->subMonthNoOverflow()->startOfMonth()->toDateString());
        $inactivo = $this->crearMiembro('Carla Inactiva', 'Inactivo', now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString());

        // B. Ministerios: uno con líder y otro sin líder.
        $cargo = DB::table('cargos')->insertGetId([
            'nombre' => 'Pastor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lider = DB::table('lideres_iglesia')->insertGetId([
            'id_cargo' => $cargo,
            'nombre' => 'Líder Uno',
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ministerio = DB::table('ministerios')->insertGetId([
            'nombre' => 'Alabanza',
            'id_lider_iglesia' => $lider,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ministerios')->insert([
            'nombre' => 'Jóvenes',
            'id_lider_iglesia' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actividad vencida y sin completar + seguimiento con avance bajo.
        $actividad = DB::table('actividades')->insertGetId([
            'id_ministerio' => $ministerio,
            'nombre' => 'Ensayo vencido',
            'fecha' => now()->subWeek()->toDateString(),
            'estado' => 'Programado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('seguimiento_actividades')->insert([
            'id_actividad' => $actividad,
            'fecha_registro' => now()->subDays(3)->toDateString(),
            'responsable' => 'Líder Uno',
            'porcentaje_avance' => 40,
            'estado' => 'En Progreso',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // C y E. Curso con 2 inscripciones activas y pagos parciales.
        $curso = $this->crearCurso('Discipulado', 10, 100.0);

        $inscripcion1 = DB::table('inscripciones')->insertGetId([
            'miembro_id' => $activo1,
            'curso_id' => $curso,
            'fecha_inscripcion' => $inicioMes,
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inscripcion2 = DB::table('inscripciones')->insertGetId([
            'miembro_id' => $activo2,
            'curso_id' => $curso,
            'fecha_inscripcion' => $inicioMes,
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$inscripcion1, 100.0], [$inscripcion2, 40.0]] as [$inscripcion, $monto]) {
            DB::table('pago_cursos')->insert([
                'inscripcion_id' => $inscripcion,
                'monto' => $monto,
                'fecha_pago' => $hoy,
                'metodo_pago' => 'Efectivo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // C. Una sesión con 2 presentes y 1 ausente.
        $sesion = DB::table('sesiones')->insertGetId([
            'id_curso' => $curso,
            'fecha' => $hoy,
            'hora' => '19:00:00',
            'tema' => 'Clase 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$activo1, 'Presente'], [$activo2, 'Tardanza'], [$inactivo, 'Ausente']] as [$miembro, $estado]) {
            DB::table('asistencias_sesion')->insert([
                'miembro_id' => $miembro,
                'sesion_id' => $sesion,
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // D. Un ingreso y un egreso del mes.
        DB::table('ingresos')->insert([
            'monto_total' => 100.0,
            'fecha' => $hoy,
            'tipo' => 'Diezmo',
            'metodo_pago' => 'Efectivo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('egresos')->insert([
            'tipo_egreso' => 'Servicios',
            'monto' => 50.0,
            'fecha' => $hoy,
            'responsable' => 'Tesorero',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // F. Un contrato que vence en 10 días.
        DB::table('contratos')->insert([
            'id_miembro' => $activo1,
            'tipo_compensacion' => 'Salario',
            'salario' => 500.0,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'fecha_fin' => now()->addDays(10)->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('hoy', 'inicioMes', 'finMes');
    }

    private function usuarioConRol(string $nombreRol): User
    {
        $rol = Role::firstOrCreate(
            ['nombre' => $nombreRol],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $user = User::factory()->create();
        $user->roles()->attach($rol->id);

        return $user;
    }

    public function test_calcula_los_kpis_de_membresia_academia_finanzas_y_cobranza(): void
    {
        ['hoy' => $hoy, 'inicioMes' => $inicioMes, 'finMes' => $finMes] = $this->sembrarDatos();

        $membresia = $this->kpis->membresia($inicioMes, $finMes);
        $this->assertSame(3, $membresia['total']);
        $this->assertSame(2, $membresia['activos']);
        $this->assertSame(66.7, $membresia['pct_activos']);
        $this->assertSame(1, $membresia['altas_mes']);

        $academia = $this->kpis->academia($inicioMes, $finMes);
        $this->assertSame(2, $academia['inscritos']);
        $this->assertSame(20.0, $academia['ocupacion_pct']);
        $this->assertSame(3, $academia['registros']);
        $this->assertSame(66.7, $academia['asistencia_pct']);

        $finanzas = $this->kpis->finanzas($inicioMes, $finMes);
        $this->assertSame(100.0, $finanzas['ingresos']);
        $this->assertSame(50.0, $finanzas['egresos']);
        $this->assertSame(50.0, $finanzas['saldo']);
        $this->assertSame(0.0, $finanzas['pct_personal']);
        $this->assertSame('Diezmo', $finanzas['composicion'][0]['tipo']);

        $cobranza = $this->kpis->cobranza();
        $this->assertSame(200.0, $cobranza['esperado']);
        $this->assertSame(140.0, $cobranza['cobrado']);
        $this->assertSame(60.0, $cobranza['saldo']);
        $this->assertSame(70.0, $cobranza['pct']);
        $this->assertSame(1, $cobranza['inscripciones_con_saldo']);
    }

    public function test_calcula_alertas_de_actividades_contratos_y_seguridad(): void
    {
        ['hoy' => $hoy, 'inicioMes' => $inicioMes, 'finMes' => $finMes] = $this->sembrarDatos();

        // Usuario sin rol: alimenta el KPI G2.
        User::factory()->create();

        $ministerios = $this->kpis->ministerios($inicioMes, $finMes, $hoy);
        $this->assertSame(2, $ministerios['total']);
        $this->assertSame(1, $ministerios['sin_lider']);
        $this->assertSame(1, $ministerios['atrasadas']);
        $this->assertSame('Ensayo vencido', $ministerios['atrasadas_lista'][0]['nombre']);

        $personal = $this->kpis->personal($hoy);
        $this->assertSame(1, $personal['total']);
        $this->assertSame(1, $personal['por_vencer']);
        $this->assertSame(10, $personal['por_vencer_lista'][0]['dias']);

        $seguridad = $this->kpis->seguridad();
        $this->assertSame(1, $seguridad['sin_rol']);
    }

    public function test_el_dashboard_filtra_los_kpis_por_rol(): void
    {
        ['hoy' => $hoy, 'inicioMes' => $inicioMes, 'finMes' => $finMes] = $this->sembrarDatos();

        $administrador = $this->kpis->dashboard($this->usuarioConRol('Administrador'), $inicioMes, $finMes, $hoy);
        $this->assertArrayHasKey('membresia', $administrador);
        $this->assertArrayHasKey('finanzas', $administrador);
        $this->assertArrayHasKey('cobranza', $administrador);
        $this->assertArrayHasKey('seguridad', $administrador);
        $this->assertArrayHasKey('calidad', $administrador);

        $secretario = $this->kpis->dashboard($this->usuarioConRol('Secretario'), $inicioMes, $finMes, $hoy);
        $this->assertArrayHasKey('membresia', $secretario);
        $this->assertArrayHasKey('ministerios', $secretario);
        $this->assertArrayHasKey('academia', $secretario);
        $this->assertArrayNotHasKey('finanzas', $secretario);
        $this->assertArrayNotHasKey('seguridad', $secretario);
        $this->assertArrayNotHasKey('calidad', $secretario);

        $tesorero = $this->kpis->dashboard($this->usuarioConRol('Tesorero'), $inicioMes, $finMes, $hoy);
        $this->assertArrayHasKey('finanzas', $tesorero);
        $this->assertArrayHasKey('cobranza', $tesorero);
        $this->assertArrayNotHasKey('membresia', $tesorero);
        $this->assertArrayNotHasKey('seguridad', $tesorero);
        $this->assertArrayNotHasKey('calidad', $tesorero);
    }
}
