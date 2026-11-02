<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Importa cobertura, resultados de pruebas y hallazgos SAST a las tablas
 * `metricas_calidad` y `hallazgos_seguridad`.
 *
 * Los archivos ausentes se informan como advertencia, nunca como error: el
 * comando debe poder ejecutarse en entornos donde aún no se generan reportes.
 */
class ImportarCalidad extends Command
{
    protected $signature = 'kpi:importar-calidad
                            {--coverage= : Ruta al reporte de cobertura (por defecto coverage.xml)}
                            {--junit= : Ruta al reporte JUnit (por defecto build/logs/junit.xml)}
                            {--sast= : Ruta al reporte SAST (por defecto resultados_sast.json)}';

    protected $description = 'Importa cobertura, pruebas y hallazgos de seguridad para el bloque de Calidad';

    public function handle(): int
    {
        $capturadoEn = now();
        $encontrado = false;

        $coverage = $this->opcion('coverage', 'coverage.xml');
        $junit = $this->opcion('junit', 'build/logs/junit.xml');
        $sast = $this->opcion('sast', 'resultados_sast.json');

        if (File::exists($coverage)) {
            $encontrado = true;
            $this->importarCobertura($coverage, $capturadoEn);
        } else {
            $this->components->warn("No se encontró {$coverage}; se omite la cobertura.");
        }

        if (File::exists($junit)) {
            $encontrado = true;
            $this->importarPruebas($junit, $capturadoEn);
        } else {
            $this->components->warn("No se encontró {$junit}; se omiten los resultados de pruebas.");
        }

        if (File::exists($sast)) {
            $encontrado = true;
            $this->importarHallazgos($sast);
        } else {
            $this->components->warn("No se encontró {$sast}; se omiten los hallazgos SAST.");
        }

        if (! $encontrado) {
            $this->components->warn('No hay reportes para importar. El bloque de Calidad quedará sin datos.');

            return self::SUCCESS;
        }

        $this->components->info('Importación de calidad completada.');

        return self::SUCCESS;
    }

    private function importarCobertura(string $ruta, $capturadoEn): void
    {
        try {
            $xml = simplexml_load_file($ruta);
        } catch (Throwable $e) {
            $this->components->warn('No se pudo leer coverage.xml: '.$e->getMessage());

            return;
        }

        if ($xml === false) {
            $this->components->warn('coverage.xml no es un XML válido.');

            return;
        }

        $metricas = $xml->xpath('/coverage/project/metrics');
        $porcentaje = null;

        if (is_array($metricas) && isset($metricas[0])) {
            $statements = (int) ($metricas[0]['statements'] ?? 0);
            $cubiertos = (int) ($metricas[0]['coveredstatements'] ?? 0);

            if ($statements > 0) {
                $porcentaje = round($cubiertos / $statements * 100, 2);
            }
        }

        if ($porcentaje === null && isset($xml['line-rate'])) {
            $porcentaje = round((float) $xml['line-rate'] * 100, 2);
        }

        $porcentaje ??= 0.0;

        $this->guardarMetrica('coverage', 'cobertura', $porcentaje, $capturadoEn, [
            'archivo' => 'coverage.xml',
        ]);

        $this->components->info('Cobertura: '.$porcentaje.'%');
    }

    private function importarPruebas(string $ruta, $capturadoEn): void
    {
        try {
            $xml = simplexml_load_file($ruta);
        } catch (Throwable $e) {
            $this->components->warn('No se pudo leer junit.xml: '.$e->getMessage());

            return;
        }

        if ($xml === false) {
            $this->components->warn('junit.xml no es un XML válido.');

            return;
        }

        // Contar nodos reales evita depender de atributos agregados que
        // distintas herramientas escriben en la raíz o en cada suite.
        $tests = count($xml->xpath('//testcase') ?: []);
        $fallos = count($xml->xpath('//failure') ?: [])
            + count($xml->xpath('//error') ?: []);

        $this->guardarMetrica('junit', 'tests', $tests, $capturadoEn, ['archivo' => 'build/logs/junit.xml']);
        $this->guardarMetrica('junit', 'fallos', $fallos, $capturadoEn, ['archivo' => 'build/logs/junit.xml']);

        $this->components->info("Pruebas: {$tests}, fallos: {$fallos}");
    }

    private function importarHallazgos(string $ruta): void
    {
        try {
            $contenido = File::get($ruta);
            $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->components->warn('No se pudo leer resultados_sast.json: '.$e->getMessage());

            return;
        }

        $hallazgos = $this->extraerHallazgos($datos);
        $nuevos = 0;
        $actualizados = 0;

        foreach ($hallazgos as $hallazgo) {
            if (! is_array($hallazgo)) {
                continue;
            }

            $regla = $this->texto($hallazgo, ['rule', 'rule_id', 'check', 'title', 'name']);
            $archivo = $this->texto($hallazgo, ['file', 'path', 'filename', 'location.path']);
            $linea = $this->entero($hallazgo, ['line', 'start_line', 'location.line']);
            $severidad = strtolower($this->texto($hallazgo, ['severity', 'level', 'risk']) ?? 'media');

            $fingerprint = hash('sha256', implode('|', [
                $this->texto($hallazgo, ['source', 'tool', 'scanner']) ?? 'sast',
                $regla ?? '',
                $archivo ?? '',
                (string) ($linea ?? ''),
            ]));

            $existente = DB::table('hallazgos_seguridad')->where('fingerprint', $fingerprint)->first();

            $datosFila = [
                'fuente' => $this->texto($hallazgo, ['source', 'tool', 'scanner']) ?? 'sast',
                'severidad' => $severidad,
                'regla' => $regla,
                'archivo' => $archivo,
                'linea' => $linea,
                'metadata' => json_encode($hallazgo, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($existente) {
                DB::table('hallazgos_seguridad')->where('id', $existente->id)->update($datosFila);
                $actualizados++;
            } else {
                DB::table('hallazgos_seguridad')->insert($datosFila + [
                    'fingerprint' => $fingerprint,
                    'estado' => 'abierto',
                    'created_at' => now(),
                ]);
                $nuevos++;
            }
        }

        $this->components->info("Hallazgos: {$nuevos} nuevos, {$actualizados} actualizados.");
    }

    /**
     * @param  mixed  $datos
     * @return array<int, mixed>
     */
    private function extraerHallazgos($datos): array
    {
        if (! is_array($datos)) {
            return [];
        }

        foreach (['findings', 'results', 'vulnerabilities', 'issues'] as $clave) {
            if (isset($datos[$clave]) && is_array($datos[$clave])) {
                return array_values($datos[$clave]);
            }
        }

        return array_is_list($datos) ? $datos : [];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarMetrica(string $fuente, string $metrica, float|int $valor, $capturadoEn, array $metadata = []): void
    {
        DB::table('metricas_calidad')->insert([
            'fuente' => $fuente,
            'metrica' => $metrica,
            'valor' => $valor,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'capturado_en' => $capturadoEn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, string>  $claves
     */
    private function texto(array $datos, array $claves): ?string
    {
        foreach ($claves as $clave) {
            if (str_contains($clave, '.')) {
                $valor = data_get($datos, $clave);

                if (is_string($valor) && $valor !== '') {
                    return $valor;
                }

                continue;
            }

            if (isset($datos[$clave]) && is_string($datos[$clave]) && $datos[$clave] !== '') {
                return $datos[$clave];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, string>  $claves
     */
    private function entero(array $datos, array $claves): ?int
    {
        foreach ($claves as $clave) {
            $valor = str_contains($clave, '.') ? data_get($datos, $clave) : ($datos[$clave] ?? null);

            if (is_numeric($valor)) {
                return (int) $valor;
            }
        }

        return null;
    }

    /**
     * Resuelve una ruta opcional relativa a la raíz del proyecto.
     */
    private function opcion(string $nombre, string $defecto): string
    {
        $valor = $this->option($nombre);

        if (! is_string($valor) || $valor === '') {
            return base_path($defecto);
        }

        return str_starts_with($valor, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $valor)
            ? $valor
            : base_path($valor);
    }
}
