<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\CategoriaFinanciera;
use App\Models\Contrato;
use App\Models\Ingreso;
use App\Models\LiderIglesia;
use App\Models\Miembro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas de validación del formulario Registrar Egreso (EgresoRequest).
 */
class EgresoRegistrarValidacionesTest extends TestCase
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

        $this->admin = User::factory()->create(['email' => 'admin_validar_egreso@proyecto.com']);
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
            'categoria_id' => CategoriaFinanciera::query()
                ->where('nombre', 'Salarios')
                ->where('naturaleza', CategoriaFinanciera::NATURALEZA_EGRESO)
                ->value('id'),
            'tipo_egreso' => 'Salarios',
            'monto' => 500,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Pago de salario',
            'responsable' => self::RESPONSABLE,
            'comprobante' => 'REC-001',
        ], $override);
    }

    public static function camposInvalidos(): array
    {
        return [
            'tipo de egreso obligatorio' => [['tipo_egreso' => ''], 'tipo_egreso'],
            'monto obligatorio' => [['monto' => ''], 'monto'],
            'fecha obligatoria' => [['fecha' => ''], 'fecha'],
            'responsable obligatorio' => [['responsable' => ''], 'responsable'],
            'monto no numérico' => [['monto' => 'abc'], 'monto'],
            'monto negativo' => [['monto' => -1], 'monto'],
            'fecha inválida' => [['fecha' => 'no-es-fecha'], 'fecha'],
            'responsable inexistente' => [['responsable' => 'Nadie'], 'responsable'],
            'tipo de egreso inválido' => [['tipo_egreso' => 'Tipo inexistente'], 'tipo_egreso'],
            'contrato inexistente' => [['id_contrato' => 99999], 'id_contrato'],
            'líder inexistente' => [['id_lider_iglesia' => 99999], 'id_lider_iglesia'],
        ];
    }

    #[DataProvider('camposInvalidos')]
    public function test_rechaza_datos_invalidos(array $override, string $campo): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos($override))
            ->assertSessionHasErrors($campo);

        $this->assertDatabaseCount('egresos', 0);
    }

    public function test_rechaza_contrato_y_lider_simultaneos(): void
    {
        $miembro = Miembro::create([
            'nombre' => 'Trabajador Contrato',
            'fecha_ingreso' => now()->subYear()->toDateString(),
            'estado' => 'Activo',
        ]);

        $contrato = Contrato::create([
            'id_miembro' => $miembro->id,
            'tipo_compensacion' => 'Salario',
            'salario' => 500,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
        ]);

        $lider = LiderIglesia::where('nombre', self::RESPONSABLE)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos([
                'id_contrato' => $contrato->id,
                'id_lider_iglesia' => $lider->id,
            ]))
            ->assertSessionHasErrors('id_contrato');

        $this->assertDatabaseCount('egresos', 0);
    }

    public function test_acepta_descripcion_y_comprobante_opcionales(): void
    {
        $this->actingAs($this->admin)
            ->post(route('egresos.store'), $this->datos([
                'descripcion' => null,
                'comprobante' => null,
            ]))
            ->assertRedirect(route('egresos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('egresos', 1);
    }
}
