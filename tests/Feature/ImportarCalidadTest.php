<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Importación de reportes de calidad (cobertura, JUnit y SAST).
 */
class ImportarCalidadTest extends TestCase
{
    use RefreshDatabase;

    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = storage_path('framework/testing/calidad');

        File::ensureDirectoryExists($this->directorio);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);

        parent::tearDown();
    }

    private function ruta(string $nombre): string
    {
        return $this->directorio.DIRECTORY_SEPARATOR.$nombre;
    }

    private function ejecutar(array $opciones = []): string
    {
        Artisan::call('kpi:importar-calidad', $opciones);

        return Artisan::output();
    }

    public function test_archivos_ausentes_generan_advertencias_sin_fallar(): void
    {
        $salida = $this->ejecutar([
            '--coverage' => $this->ruta('no-existe-coverage.xml'),
            '--junit' => $this->ruta('no-existe-junit.xml'),
            '--sast' => $this->ruta('no-existe-sast.json'),
        ]);

        $this->assertStringContainsString('No se encontró', $salida);
        $this->assertStringContainsString('No hay reportes para importar', $salida);
        $this->assertDatabaseCount('metricas_calidad', 0);
    }

    public function test_hallazgos_sast_se_deduplican_por_fingerprint(): void
    {
        $sast = $this->ruta('sast.json');

        File::put($sast, json_encode([
            'results' => [
                [
                    'source' => 'semgrep',
                    'rule' => 'php.lang.security.eval',
                    'file' => 'app/Services/Peligro.php',
                    'line' => 42,
                    'severity' => 'alta',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $opciones = [
            '--coverage' => $this->ruta('sin-coverage.xml'),
            '--junit' => $this->ruta('sin-junit.xml'),
            '--sast' => $sast,
        ];

        $this->ejecutar($opciones);
        $this->ejecutar($opciones);

        $this->assertSame(1, DB::table('hallazgos_seguridad')->count());

        $hallazgo = DB::table('hallazgos_seguridad')->first();

        $this->assertSame(64, strlen($hallazgo->fingerprint));
        $this->assertSame('semgrep', $hallazgo->fuente);
        $this->assertSame('alta', $hallazgo->severidad);
        $this->assertSame('abierto', $hallazgo->estado);
    }

    public function test_importa_los_reportes_reales_del_proyecto(): void
    {
        foreach (['coverage.xml', 'build/logs/junit.xml', 'resultados_sast.json'] as $archivo) {
            if (! File::exists(base_path($archivo))) {
                $this->markTestSkipped("No existe {$archivo} en este entorno.");
            }
        }

        $salida = $this->ejecutar();

        $this->assertStringContainsString('Importación de calidad completada', $salida);
        $this->assertDatabaseHas('metricas_calidad', ['fuente' => 'coverage', 'metrica' => 'cobertura']);
        $this->assertDatabaseHas('metricas_calidad', ['fuente' => 'junit', 'metrica' => 'tests']);

        $cobertura = (float) DB::table('metricas_calidad')
            ->where('fuente', 'coverage')
            ->where('metrica', 'cobertura')
            ->value('valor');

        $pruebas = (int) DB::table('metricas_calidad')
            ->where('fuente', 'junit')
            ->where('metrica', 'tests')
            ->value('valor');

        $this->assertGreaterThan(0, $cobertura);
        $this->assertGreaterThan(0, $pruebas);
    }
}
