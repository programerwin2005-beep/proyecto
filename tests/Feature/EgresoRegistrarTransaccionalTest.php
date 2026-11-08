<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\CategoriaFinanciera;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Casos transaccionales de la función Registrar Egreso (EgresoController::store).
 *
 * CP1 a CP6 reproducen la plantilla de cobertura de instrucciones del módulo
 * Administrar Egresos y comprueban el registro, el control de saldo y la
 * asignación automática de la categoría financiera.
 */
class EgresoRegistrarTransaccionalTest extends TestCase
{
    use RefreshDatabase;

    private const RESPONSABLE = 'Líder de Prueba';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'nombre' => 'Administrador',
            'estado' => 'Activo',
            'nivel_acceso' => 1,
        ]);

        $this->admin = User::factory()->create(['email' => 'admin_registrar_egreso@proyecto.com']);
        $this->admin->roles()->attach($role->id);

        $cargo = Cargo::create(['nombre' => 'Tesorero', 'nivel_jerarquico' => 1]);

        LiderIglesia::create([
            'id_cargo' => $cargo->id,
            'nombre' => self::RESPONSABLE,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
        ]);

        Ingreso::create([
            'monto_total' => 2000,
            'fecha' => now()->toDateString(),
            'tipo' => 'Ofrenda',
            'metodo_pago' => 'Efectivo',
        ]);
    }

    private function datos(array $override = []): array
    {
        return array_merge([
            'id_contrato' => null,
            'id_lider_iglesia' => null,
            'categoria_id' => $this->categoria('Salarios'),
            'tipo_egreso' => 'Salarios',
            'monto' => 500,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Pago de salario',
            'responsable' => self::RESPONSABLE,
            'comprobante' => 'REC-001',
        ], $override);
    }

    private function categoria(string $nombre): ?int
    {
        return CategoriaFinanciera::query()
            ->where('nombre', $nombre)
            ->where('naturaleza', CategoriaFinanciera::NATURALEZA_EGRESO)
            ->value('id');
    }

    public function test_cp1_registra_egreso_con_categoria_seleccionada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos())
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', [
            'tipo_egreso' => 'Salarios',
            'monto' => 500,
            'responsable' => self::RESPONSABLE,
            'categoria_id' => $this->categoria('Salarios'),
        ]);
    }

    public function test_cp2_rechaza_egreso_mayor_al_saldo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos(['monto' => 3000]))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseCount('egresos', 0);
    }

    public function test_cp3_asigna_categoria_automaticamente_sin_seleccion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos([
                'tipo_egreso' => 'Servicios Básicos',
                'categoria_id' => null,
                'monto' => 300,
            ]))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', [
            'tipo_egreso' => 'Servicios Básicos',
            'monto' => 300,
            'categoria_id' => $this->categoria('Servicios Básicos'),
        ]);
    }

    public function test_cp4_acepta_monto_igual_al_saldo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos(['monto' => 2000]))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', ['monto' => 2000]);
    }

    public function test_cp5_rechaza_un_centavo_sobre_el_saldo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos(['monto' => 2000.01]))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseCount('egresos', 0);
    }

    public function test_cp6_acepta_monto_cero(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos(['monto' => 0]))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('egresos', ['monto' => 0]);
    }
}
