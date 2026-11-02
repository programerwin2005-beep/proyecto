<?php

namespace Database\Seeders;

use App\Enums\TipoEgreso;
use App\Enums\TipoIngreso;
use App\Models\CategoriaFinanciera;
use Illuminate\Database\Seeder;

/**
 * Crea (o restaura) las categorías financieras canónicas a partir de los
 * enums de ingresos y egresos. Es idempotente.
 */
class CategoriasFinancierasSeeder extends Seeder
{
    public function run(): void
    {
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
}
