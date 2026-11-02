<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use App\Enums\TipoIngreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_actividad' => 'nullable|exists:actividades,id',
            'id_culto' => 'nullable|exists:cultos,id',
            'categoria_id' => 'nullable|exists:categorias_financieras,id',
            'monto_total' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'tipo' => ['required', Rule::enum(TipoIngreso::class)],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
        ];
    }
}
