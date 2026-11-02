<?php

namespace App\Http\Requests;

use App\Enums\EstadoActividad;
use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActividadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_ministerio' => 'required|exists:ministerios,id',
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'fecha' => 'required|date',
            'lugar' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'estado' => ['required', Rule::enum(EstadoActividad::class)],
        ];
    }
}
