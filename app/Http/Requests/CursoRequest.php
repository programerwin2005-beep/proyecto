<?php

namespace App\Http\Requests;

use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;

class CursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'f_inicio' => 'required|date',
            'f_fin' => 'nullable|date|after_or_equal:f_inicio',
            'cupo_max' => 'required|integer|min:1',
            'dias_semana' => 'nullable|array',
            'dias_semana.*' => 'integer|between:1,7',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|required_with:hora_inicio|date_format:H:i|after:hora_inicio',
            'tiene_pago' => 'boolean',
            'monto_inscripcion' => 'required|numeric|min:0',
        ];
    }
}
