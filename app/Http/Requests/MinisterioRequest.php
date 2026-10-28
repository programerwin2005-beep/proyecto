<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MinisterioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'id_lider_iglesia' => 'nullable|exists:lideres_iglesia,id',
        ];
    }

    public function messages(): array
    {
        return [
            'id_lider_iglesia.exists' => 'El líder seleccionado no existe.',
        ];
    }
}
