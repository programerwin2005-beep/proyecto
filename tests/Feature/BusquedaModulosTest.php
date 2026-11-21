<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Cargo;
use App\Models\Culto;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Egreso;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Ministerio;
use App\Models\Role;
use App\Models\SeguimientoActividad;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada listado principal del sistema permite buscar por su campo principal.
 */
class BusquedaModulosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Miembro $miembroBuscado;

    private Miembro $miembroOtro;

    private Cargo $cargoBuscado;

    private LiderIglesia $liderBuscado;

    private Ministerio $ministerioBuscado;

    private Actividad $actividadBuscada;

    private Actividad $actividadOtra;

    private Curso $cursoBuscado;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['estado' => 'Activo', 'nivel_acceso' => 1]
        );

        $this->admin = User::factory()->create([
            'name' => 'Admin Busquedas',
            'email' => 'admin.busquedas@proyecto.com',
        ]);
        $this->admin->roles()->attach($rol->id);

        $this->miembroBuscado = Miembro::create([
            'nombre' => 'Miembro Buscado',
            'email' => 'miembro.buscado@proyecto.com',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->miembroOtro = Miembro::create([
            'nombre' => 'Miembro Otro',
            'email' => 'miembro.otro@proyecto.com',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->cargoBuscado = Cargo::create([
            'nombre' => 'Cargo Buscado',
            'descripcion' => 'Descripcion cargo buscado',
            'nivel_jerarquico' => 1,
        ]);

        $cargoOtro = Cargo::create([
            'nombre' => 'Cargo Otro',
            'descripcion' => 'Descripcion cargo otro',
            'nivel_jerarquico' => 2,
        ]);

        $this->liderBuscado = LiderIglesia::create([
            'id_cargo' => $this->cargoBuscado->id,
            'nombre' => 'Lider Buscado',
            'telefono' => '70000001',
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);

        LiderIglesia::create([
            'id_cargo' => $cargoOtro->id,
            'nombre' => 'Lider Otro',
            'telefono' => '70000002',
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);

        $this->ministerioBuscado = Ministerio::create([
            'nombre' => 'Ministerio Buscado',
            'descripcion' => 'Descripcion ministerio buscado',
            'id_lider_iglesia' => $this->liderBuscado->id,
        ]);

        $ministerioOtro = Ministerio::create([
            'nombre' => 'Ministerio Otro',
            'descripcion' => 'Descripcion ministerio otro',
            'id_lider_iglesia' => $this->liderBuscado->id,
        ]);

        $this->actividadBuscada = Actividad::create([
            'id_ministerio' => $this->ministerioBuscado->id,
            'nombre' => 'Actividad Buscada',
            'fecha' => now()->toDateString(),
            'estado' => 'Programado',
        ]);

        $this->actividadOtra = Actividad::create([
            'id_ministerio' => $ministerioOtro->id,
            'nombre' => 'Actividad Otra',
            'fecha' => now()->toDateString(),
            'estado' => 'Programado',
        ]);

        Culto::create([
            'id_lider_iglesia' => $this->liderBuscado->id,
            'nombre' => 'Culto Buscado',
            'dia_semana' => 'Domingo',
            'hora' => '10:00',
            'descripcion' => 'Culto buscado',
        ]);

        Culto::create([
            'id_lider_iglesia' => $this->liderBuscado->id,
            'nombre' => 'Culto Otro',
            'dia_semana' => 'Lunes',
            'hora' => '18:00',
            'descripcion' => 'Culto otro',
        ]);

        $this->cursoBuscado = Curso::create([
            'nombre' => 'Curso Buscado',
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
        ]);

        Curso::create([
            'nombre' => 'Curso Otro',
            'f_inicio' => now()->toDateString(),
            'cupo_max' => 10,
        ]);

        Docente::create([
            'id_miembro' => $this->miembroBuscado->id,
            'especialidad' => 'Especialidad Unica',
            'titulo' => 'Titulo buscado',
            'activo' => true,
        ]);

        Docente::create([
            'id_miembro' => $this->miembroOtro->id,
            'especialidad' => 'Especialidad Otra',
            'titulo' => 'Titulo otro',
            'activo' => true,
        ]);

        SeguimientoActividad::create([
            'id_actividad' => $this->actividadBuscada->id,
            'fecha_registro' => now()->toDateString(),
            'responsable' => 'Responsable Buscado',
            'porcentaje_avance' => 50,
            'estado' => 'En Progreso',
        ]);

        SeguimientoActividad::create([
            'id_actividad' => $this->actividadOtra->id,
            'fecha_registro' => now()->toDateString(),
            'responsable' => 'Responsable Otro',
            'porcentaje_avance' => 20,
            'estado' => 'En Progreso',
        ]);

        Sesion::create([
            'id_curso' => $this->cursoBuscado->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00',
            'tema' => 'Sesion Buscada',
        ]);

        Sesion::create([
            'id_curso' => $this->cursoBuscado->id,
            'fecha' => now()->toDateString(),
            'hora' => '11:00',
            'tema' => 'Sesion Otro',
        ]);

        Ingreso::create([
            'id_actividad' => $this->actividadBuscada->id,
            'monto_total' => 100,
            'fecha' => now()->toDateString(),
            'tipo' => 'Ofrenda',
            'metodo_pago' => 'Efectivo',
        ]);

        Ingreso::create([
            'id_actividad' => $this->actividadOtra->id,
            'monto_total' => 200,
            'fecha' => now()->toDateString(),
            'tipo' => 'Ofrenda',
            'metodo_pago' => 'Efectivo',
        ]);

        Egreso::create([
            'tipo_egreso' => 'Mantenimiento',
            'monto' => 50,
            'fecha' => now()->toDateString(),
            'responsable' => 'Responsable Egreso Buscado',
        ]);

        Egreso::create([
            'tipo_egreso' => 'Mantenimiento',
            'monto' => 70,
            'fecha' => now()->toDateString(),
            'responsable' => 'Responsable Egreso Otro',
        ]);
    }

    public function test_todos_los_listados_principales_muestran_la_barra_de_busqueda(): void
    {
        $rutas = [
            'miembros.index', 'cultos.index', 'actividades.index', 'cursos.index',
            'cargos.index', 'lideres.index', 'ministerios.index', 'docentes.index',
            'seguimiento.index', 'sesiones.index', 'ingresos.index', 'egresos.index',
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->admin)
                ->get(route($ruta))
                ->assertOk()
                ->assertSee('name="q"', false);
        }
    }

    public function test_busqueda_filtra_por_el_campo_principal_de_cada_modulo(): void
    {
        $casos = [
            ['miembros.index', 'Miembro Buscado', 'Miembro Otro'],
            ['cultos.index', 'Culto Buscado', 'Culto Otro'],
            ['actividades.index', 'Actividad Buscada', 'Actividad Otra'],
            ['cursos.index', 'Curso Buscado', 'Curso Otro'],
            ['cargos.index', 'Cargo Buscado', 'Cargo Otro'],
            ['lideres.index', 'Lider Buscado', 'Lider Otro'],
            ['ministerios.index', 'Ministerio Buscado', 'Ministerio Otro'],
            ['docentes.index', 'Especialidad Unica', 'Especialidad Otra'],
            ['seguimiento.index', 'Responsable Buscado', 'Responsable Otro'],
            ['sesiones.index', 'Sesion Buscada', 'Sesion Otro'],
            ['ingresos.index', 'Actividad Buscada', 'Actividad Otra'],
            ['egresos.index', 'Responsable Egreso Buscado', 'Responsable Egreso Otro'],
        ];

        foreach ($casos as [$ruta, $buscado, $otro]) {
            $this->actingAs($this->admin)
                ->get(route($ruta, ['q' => $buscado]))
                ->assertOk()
                ->assertSee($buscado)
                ->assertDontSee($otro);
        }
    }
}
