<?php

namespace App\Http\Requests;

use App\Enums\TipoEgreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_contrato' => [
                'nullable',
                'exists:contratos,id',
                // Un egreso se paga por contrato o directamente a un líder,
                // nunca por ambas vías (evita duplicar el gasto en los KPI).
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->filled('id_lider_iglesia')) {
                        $fail('Un egreso no puede asociarse a un contrato y a un líder a la vez.');
                    }
                },
            ],
            'id_lider_iglesia' => 'nullable|exists:lideres_iglesia,id',
            'categoria_id' => 'nullable|exists:categorias_financieras,id',
            'tipo_egreso' => ['required', Rule::enum(TipoEgreso::class)],
            'monto' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'descripcion' => 'nullable|string',
            'responsable' => 'required|string|exists:lideres_iglesia,nombre',
            'comprobante' => 'nullable|string|max:255',
        ];
    }
}
