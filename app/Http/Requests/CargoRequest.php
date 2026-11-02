<?php

namespace App\Http\Requests;

use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;

class CargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'descripcion' => 'nullable|string',
            'nivel_jerarquico' => 'required|integer|min:1',
        ];
    }
}
