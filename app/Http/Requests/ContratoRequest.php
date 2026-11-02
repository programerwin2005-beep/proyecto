<?php

namespace App\Http\Requests;

use App\Enums\TipoCompensacion;
use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignorarId = $this->route('contrato');

        return [
            'id_miembro' => [
                'required',
                'exists:miembros,id',
                // Laravel invoca la regla como ($attribute, $value, $fail): la firma es obligatoria.
                function ($attribute, $value, $fail) use ($ignorarId) { // NOSONAR
                    if (Contrato::tieneSolapamiento(
                        (int) $value,
                        $this->input('fecha_inicio'),
                        $this->input('fecha_fin'),
                        $ignorarId ? (int) $ignorarId : null
                    )) {
                        $fail('El miembro ya tiene un contrato cuyas fechas se solapan con las ingresadas. Cierra o renueva el contrato anterior.');
                    }
                },
            ],
            'id_lider_iglesia' => 'required|exists:lideres_iglesia,id',
            'tipo_compensacion' => ['required', Rule::enum(TipoCompensacion::class)],
            'salario' => [
                'required_if:tipo_compensacion,Salario,Bono',
                'nullable',
                'numeric',
                'min:0',
            ],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ];
    }

    public function messages(): array
    {
        return [
            'salario.required_if' => 'El salario es obligatorio para contratos de tipo Salario o Bono.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }
}
