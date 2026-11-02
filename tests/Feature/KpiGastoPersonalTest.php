<?php

namespace Tests\Feature;

use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F3: el gasto de personal debe incluir contratos y pagos directos a líderes,
 * contando cada egreso una sola vez.
 */
class KpiGastoPersonalTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(): array
    {
        $hoy = now()->toDateString();
        $desde = now()->startOfMonth()->toDateString();
        $hasta = now()->endOfMonth()->toDateString();

        $miembro = DB::table('miembros')->insertGetId([
            'nombre' => 'Trabajador Uno',
            'fecha_ingreso' => $hoy,
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cargo = DB::table('cargos')->insertGetId([
            'nombre' => 'Pastor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lider = DB::table('lideres_iglesia')->insertGetId([
            'id_cargo' => $cargo,
            'nombre' => 'Líder Directo',
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contrato = DB::table('contratos')->insertGetId([
            'id_miembro' => $miembro,
            'tipo_compensacion' => 'Salario',
            'salario' => 1000,
            'fecha_inicio' => now()->subYear()->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('hoy', 'desde', 'hasta', 'contrato', 'lider');
    }

    private function egreso(string $monto, array $extra = []): void
    {
        DB::table('egresos')->insert(array_merge([
            'tipo_egreso' => 'Otros Egresos',
            'monto' => $monto,
            'fecha' => now()->toDateString(),
            'responsable' => 'Tesorero',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    public function test_gasto_personal_incluye_solo_contrato(): void
    {
        ['desde' => $desde, 'hasta' => $hasta, 'contrato' => $contrato] = $this->escenario();

        $this->egreso(100, ['id_contrato' => $contrato]);

        $finanzas = (new KpiService)->finanzas($desde, $hasta);

        $this->assertSame(100.0, $finanzas['personal']);
    }

    public function test_gasto_personal_incluye_pago_directo_a_lider(): void
    {
        ['desde' => $desde, 'hasta' => $hasta, 'lider' => $lider] = $this->escenario();

        $this->egreso(50, ['id_lider_iglesia' => $lider]);

        $finanzas = (new KpiService)->finanzas($desde, $hasta);

        $this->assertSame(50.0, $finanzas['personal']);
    }

    public function test_gasto_personal_no_duplica_egreso_con_contrato_y_lider(): void
    {
        [
            'desde' => $desde,
            'hasta' => $hasta,
            'contrato' => $contrato,
            'lider' => $lider,
        ] = $this->escenario();

        $this->egreso(100, ['id_contrato' => $contrato]);
        $this->egreso(50, ['id_lider_iglesia' => $lider]);
        $this->egreso(25, [
            'id_contrato' => $contrato,
            'id_lider_iglesia' => $lider,
        ]);
        // Gasto ajeno al personal: no debe sumarse.
        $this->egreso(30, ['tipo_egreso' => 'Mantenimiento']);

        $finanzas = (new KpiService)->finanzas($desde, $hasta);

        $this->assertSame(175.0, $finanzas['personal']);
    }
}
