<?php

namespace App\Http\Requests;

use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;

class LiderIglesiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_cargo' => 'required|exists:cargos,id',
            'nombre' => ['required', 'string', 'max:255', new SoloLetras],
            'telefono' => 'nullable|string|max:8|regex:/^[0-9 ]*$/',
            'fecha_inicio' => 'required|date',
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'id_cargo.required' => 'El cargo es obligatorio.',
            'id_cargo.exists' => 'El cargo seleccionado no existe.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no debe superar los 255 caracteres.',
            'telefono.max' => 'El teléfono no debe superar los 8 caracteres.',
            'telefono.regex' => 'El teléfono solo puede contener números y espacios.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
        ];
    }
}
