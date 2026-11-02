<?php

use App\Enums\DiaSemana;
use App\Enums\EstadoActividad;
use App\Enums\EstadoAsistencia;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMiembro;
use App\Enums\EstadoSeguimiento;
use App\Enums\MetodoPago;
use App\Enums\TipoCompensacion;
use App\Enums\TipoEgreso;
use App\Enums\TipoIngreso;
use App\Models\CategoriaFinanciera;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normaliza los valores heredados de los campos que ahora usan enums y
     * enlaza ingresos/egresos con el catálogo `categorias_financieras`.
     *
     * Los valores desconocidos NO se eliminan ni se reemplazan: se conservan
     * tal cual y se reportan para revisión manual.
     */
    public function up(): void
    {
        $this->normalizar('miembros', 'estado', $this->mapa(EstadoMiembro::valores()));

        $this->normalizar('inscripciones', 'estado', $this->mapa(EstadoInscripcion::valores()));

        $this->normalizar('actividades', 'estado', $this->mapa(EstadoActividad::valores(), [
            'Completado' => EstadoActividad::Completada->value,
            'Cancelado' => EstadoActividad::Cancelada->value,
        ]));

        $this->normalizar('seguimiento_actividades', 'estado', $this->mapa(EstadoSeguimiento::valores()));

        $this->normalizar('asistencias_sesion', 'estado', $this->mapa(EstadoAsistencia::valores()));

        $this->normalizar('contratos', 'tipo_compensacion', $this->mapa(TipoCompensacion::valores()));

        $this->normalizar('pago_cursos', 'metodo_pago', $this->mapa(MetodoPago::valores()));

        $this->normalizar('ingresos', 'metodo_pago', $this->mapa(MetodoPago::valores()));

        $this->normalizar('cultos', 'dia_semana', $this->mapa(DiaSemana::valores(), [
            'Sabado' => DiaSemana::Sabado->value,
            'Miercoles' => DiaSemana::Miercoles->value,
        ]));

        $this->normalizar('ingresos', 'tipo', $this->mapa(TipoIngreso::valores(), [
            'Inscripción Curso' => TipoIngreso::Inscripcion->value,
            'Inscripcion Curso' => TipoIngreso::Inscripcion->value,
        ]), prefijos: [
            'inscripcion curso' => TipoIngreso::Inscripcion->value,
        ]);

        $this->normalizar('egresos', 'tipo_egreso', $this->mapa(TipoEgreso::valores(), [
            'Salario' => TipoEgreso::Salarios->value,
            'Salarios / Honorarios' => TipoEgreso::Salarios->value,
            'Servicio' => TipoEgreso::ServiciosBasicos->value,
            'Ayuda Social' => TipoEgreso::AyudaComunitaria->value,
        ]));

        $this->sembrarCategorias();
        $this->enlazarCategorias();
    }

    public function down(): void
    {
        // La normalización de textos no se revierte para no destruir datos:
        // los valores canónicos siguen siendo válidos. Solo se deshace el
        // enlace al catálogo, que puede recalcularse volviendo a ejecutar up().
        if (Schema::hasTable('ingresos') && Schema::hasColumn('ingresos', 'categoria_id')) {
            DB::table('ingresos')->whereNotNull('categoria_id')->update(['categoria_id' => null]);
        }

        if (Schema::hasTable('egresos') && Schema::hasColumn('egresos', 'categoria_id')) {
            DB::table('egresos')->whereNotNull('categoria_id')->update(['categoria_id' => null]);
        }
    }

    /**
     * Construye un mapa de alias => valor canónico, incluyendo el propio valor.
     *
     * @param  array<int, string>  $canonicos
     * @param  array<string, string>  $alias
     * @return array<string, string>
     */
    private function mapa(array $canonicos, array $alias = []): array
    {
        $mapa = [];

        foreach ($canonicos as $valor) {
            $mapa[$valor] = $valor;
        }

        return $mapa + $alias;
    }

    /**
     * @param  array<string, string>  $mapa
     * @param  array<string, string>  $prefijos
     */
    private function normalizar(string $tabla, string $columna, array $mapa, array $prefijos = []): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        $indice = [];

        foreach ($mapa as $alias => $canonico) {
            $indice[$this->comparable($alias)] = $canonico;
        }

        $valores = DB::table($tabla)
            ->whereNotNull($columna)
            ->distinct()
            ->pluck($columna);

        foreach ($valores as $valor) {
            if (! is_string($valor)) {
                continue;
            }

            $comparable = $this->comparable($valor);
            $canonico = $indice[$comparable] ?? null;

            if ($canonico === null) {
                foreach ($prefijos as $prefijo => $destino) {
                    if (str_starts_with($comparable, $prefijo)) {
                        $canonico = $destino;
                        break;
                    }
                }
            }

            if ($canonico === null) {
                $this->advertir("Valor no reconocido en {$tabla}.{$columna}: '{$valor}' (se conserva).");

                continue;
            }

            if ($canonico !== $valor) {
                DB::table($tabla)->where($columna, $valor)->update([$columna => $canonico]);
            }
        }
    }

    private function sembrarCategorias(): void
    {
        if (! Schema::hasTable('categorias_financieras')) {
            return;
        }

        $definiciones = [
            CategoriaFinanciera::NATURALEZA_INGRESO => TipoIngreso::valores(),
            CategoriaFinanciera::NATURALEZA_EGRESO => TipoEgreso::valores(),
        ];

        foreach ($definiciones as $naturaleza => $nombres) {
            foreach ($nombres as $nombre) {
                $categoria = CategoriaFinanciera::withTrashed()
                    ->firstOrNew(['nombre' => $nombre, 'naturaleza' => $naturaleza]);

                $categoria->activo = true;

                if ($categoria->exists && $categoria->trashed()) {
                    $categoria->restore();
                }

                $categoria->save();
            }
        }
    }

    private function enlazarCategorias(): void
    {
        if (
            ! Schema::hasTable('categorias_financieras')
            || ! Schema::hasColumn('ingresos', 'categoria_id')
            || ! Schema::hasColumn('egresos', 'categoria_id')
        ) {
            return;
        }

        $categorias = DB::table('categorias_financieras')
            ->whereNull('deleted_at')
            ->get(['id', 'nombre', 'naturaleza']);

        $porNaturaleza = $categorias->groupBy('naturaleza');

        foreach ($porNaturaleza->get(CategoriaFinanciera::NATURALEZA_INGRESO, collect()) as $categoria) {
            DB::table('ingresos')
                ->whereNull('categoria_id')
                ->where('tipo', $categoria->nombre)
                ->update(['categoria_id' => $categoria->id]);
        }

        foreach ($porNaturaleza->get(CategoriaFinanciera::NATURALEZA_EGRESO, collect()) as $categoria) {
            DB::table('egresos')
                ->whereNull('categoria_id')
                ->where('tipo_egreso', $categoria->nombre)
                ->update(['categoria_id' => $categoria->id]);
        }
    }

    private function comparable(string $valor): string
    {
        $valor = trim(mb_strtolower($valor));

        return strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    private function advertir(string $mensaje): void
    {
        Log::warning('[normalizar-valores] '.$mensaje);

        if (defined('STDERR')) {
            fwrite(STDERR, '  ! '.$mensaje.PHP_EOL);
        }
    }
};
