<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermisosPorRolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT_VER_TODAS = 'Ver todas';

    /**
     * Crea un usuario con el rol indicado para comprobar la visibilidad de accesos.
     */
    private function usuarioConRol(string $nombreRol): User
    {
        $user = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['nombre' => $nombreRol],
            ['estado' => 'Activo', 'nivel_acceso' => 3]
        );

        $user->roles()->attach($rol->id);

        // Los roles creados dentro del test no pasan por la migración que
        // siembra permisos; se aplican aquí para reproducir el escenario real.
        (new PermisosPorRolSeeder)->sembrarPara($rol);
        $user->limpiarPermisos();

        return $user;
    }

    /**
     * Datos representativos con la misma forma que entrega DashboardController.
     */
    private function datosDashboard(): array
    {
        return [
            'stats' => [
                'miembros' => 42,
                'miembros_activos' => 30,
                'ministerios' => 5,
                'lideres' => 4,
                'cargos' => 3,
                'cultos' => 8,
                'cursos' => 6,
                'docentes' => 4,
                'inscripciones' => 25,
                'contratos_activos' => 2,
                'ingresos_mes' => 1500.5,
                'egresos_mes' => 500.25,
                'ingresos_totales' => 10000.0,
                'egresos_totales' => 7000.0,
                'balance' => 3000.0,
                'balance_mes' => 1000.25,
            ],
            'asistenciaStats' => ['presentes' => 80, 'ausentes' => 15, 'tardanzas' => 5],
            'ingresosUltimos6' => collect(['2026-09' => 1000.0, '2026-10' => 1500.5]),
            'egresosUltimos6' => collect(['2026-09' => 400.0, '2026-10' => 500.25]),
            'ingresosPorTipo' => collect(['Diezmo' => 1200.0]),
            'egresosPorTipo' => collect(['Servicios' => 300.0]),
            'proximasActividades' => collect([
                (object) [
                    'nombre' => 'Ensayo general',
                    'fecha' => '2026-10-20',
                    'lugar' => 'Templo',
                    'estado' => 'Programado',
                    'ministerio' => (object) ['nombre' => 'Alabanza'],
                ],
            ]),
            'proximasSesiones' => collect([
                (object) [
                    'tema' => 'Clase introductoria',
                    'fecha' => '2026-10-22',
                    'hora' => '19:00:00',
                    'curso' => (object) ['nombre' => 'Discipulado'],
                    'docente' => (object) ['miembro' => (object) ['nombre' => 'Juan Pérez']],
                ],
            ]),
        ];
    }

    public function test_el_dashboard_renderiza_las_secciones_principales(): void
    {
        $user = $this->usuarioConRol('Administrador');

        $this->actingAs($user)
            ->view('dashboard', $this->datosDashboard())
            ->assertSee('Dashboard')
            ->assertSee('Resumen general de la gestión de la iglesia')
            ->assertSee('Flujo Financiero')
            ->assertSee('Resumen General')
            ->assertSee('Asistencia general')
            ->assertSee('Ingresos del mes por tipo')
            ->assertSee('Próximas Actividades')
            ->assertSee('Próximas Sesiones')
            ->assertSee('Accesos Rápidos')
            ->assertSee('dash-bar--in', false)
            ->assertSee('dash-bar--out', false);
    }

    public function test_el_dashboard_muestra_los_valores_reales(): void
    {
        $user = $this->usuarioConRol('Administrador');

        $this->actingAs($user)
            ->view('dashboard', $this->datosDashboard())
            ->assertSee('Miembros registrados')
            ->assertSee('30 activos')
            ->assertSee('1,500.50')
            ->assertSee('1,000.25')
            ->assertSee('80%');
    }

    public function test_el_dashboard_muestra_estado_vacio_del_grafico_sin_movimientos(): void
    {
        $user = $this->usuarioConRol('Administrador');

        $datos = $this->datosDashboard();
        $datos['ingresosUltimos6'] = collect();
        $datos['egresosUltimos6'] = collect();

        $this->actingAs($user)
            ->view('dashboard', $datos)
            ->assertSee('Sin movimientos financieros en los últimos 6 meses.');
    }

    public function test_el_dashboard_carga_los_kpis_del_controlador(): void
    {
        $user = $this->usuarioConRol('Administrador');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Indicadores clave')
            ->assertSee('Alertas accionables');
    }

    public function test_el_administrador_ve_todos_los_accesos_rapidos(): void
    {
        $user = $this->usuarioConRol('Administrador');

        $this->actingAs($user)
            ->view('dashboard', $this->datosDashboard())
            ->assertSee(route('miembros.create'), false)
            ->assertSee(route('ingresos.create'), false)
            ->assertSee(route('contratos.create'), false)
            ->assertSee(self::TEXT_VER_TODAS);
    }

    public function test_el_secretario_no_ve_accesos_de_finanzas(): void
    {
        $user = $this->usuarioConRol('Secretario');

        $this->actingAs($user)
            ->view('dashboard', $this->datosDashboard())
            ->assertSee(route('miembros.create'), false)
            ->assertSee(self::TEXT_VER_TODAS)
            ->assertDontSee(route('ingresos.create'), false)
            ->assertDontSee(route('contratos.create'), false);
    }

    public function test_el_tesorero_no_ve_accesos_de_parametrizacion(): void
    {
        $user = $this->usuarioConRol('Tesorero');

        $this->actingAs($user)
            ->view('dashboard', $this->datosDashboard())
            ->assertSee(route('ingresos.create'), false)
            ->assertDontSee(route('miembros.create'), false)
            ->assertDontSee(route('cursos.create'), false)
            ->assertDontSee(self::TEXT_VER_TODAS);
    }
}
