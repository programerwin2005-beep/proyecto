<?php

namespace App\Http\Requests;

use App\Enums\EstadoMiembro;
use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $miembroId = $this->route('miembro');
        $miembroId = $miembroId !== null ? (int) $miembroId : null;

        return [
            'nombre' => ['required', 'string', 'max:255', new SoloLetras, 'unique:miembros,nombre,'.$miembroId],
            'email' => 'nullable|email|unique:miembros,email,'.$miembroId,
            'telefono' => 'nullable|string|max:8|regex:/^[0-9 ]*$/',
            'direccion' => 'nullable|string|max:255',
            'f_nacimiento' => 'nullable|date|before_or_equal:'.now()->subYears(13)->toDateString(),
            'sexo' => 'nullable|string|in:Masculino,Femenino',
            'fecha_ingreso' => 'required|date',
            'estado' => ['required', Rule::enum(EstadoMiembro::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un miembro con este nombre completo.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.unique' => 'El correo electrónico ya ha sido registrado.',
            'telefono.max' => 'El teléfono no debe superar los 8 caracteres.',
            'telefono.regex' => 'El teléfono solo puede contener números y espacios.',
            'direccion.max' => 'La dirección no debe superar los 255 caracteres.',
            'f_nacimiento.before_or_equal' => 'La fecha de nacimiento no es válida: se requiere tener al menos 13 años.',
            'sexo.in' => 'El sexo debe ser Masculino o Femenino.',
            'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
            'fecha_ingreso.date' => 'La fecha de ingreso debe ser una fecha válida.',
            'estado.in' => 'El estado debe ser Activo o Inactivo.',
        ];
    }
}
