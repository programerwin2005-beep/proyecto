<?php

namespace App\Http\Requests;

use App\Enums\EstadoMiembro;
use App\Models\Miembro;
use App\Rules\SoloLetras;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MinisterioRequest extends FormRequest
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
            'id_lider_iglesia' => [
                'nullable',
                Rule::exists('lideres_iglesia', 'id')->whereNull('deleted_at'),
            ],
            'miembros' => ['nullable', 'array'],
            'miembros.*' => [
                'integer',
                'distinct',
                Rule::exists('miembros', 'id')->whereNull('deleted_at'),
                function ($attribute, $value, $fail) {
                    $miembro = Miembro::find($value);

                    if (! $miembro || $miembro->estado === EstadoMiembro::Activo) {
                        return;
                    }

                    // Un miembro que ya integra el ministerio puede seguir
                    // seleccionado aunque haya quedado inactivo; así editar el
                    // ministerio no lo expulsa sin querer.
                    $ministerioId = $this->route('ministerio');

                    $yaIntegra = $ministerioId && DB::table('miembro_ministerio')
                        ->where('ministerio_id', $ministerioId)
                        ->where('miembro_id', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($yaIntegra) {
                        return;
                    }

                    $fail('El miembro seleccionado no está activo.');
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_lider_iglesia.exists' => 'El líder seleccionado no existe o está eliminado.',
            'miembros.array' => 'La lista de miembros no es válida.',
            'miembros.*.integer' => 'Uno de los miembros seleccionados no es válido.',
            'miembros.*.distinct' => 'No se puede repetir un miembro en el mismo ministerio.',
            'miembros.*.exists' => 'Uno de los miembros seleccionados no existe o está eliminado.',
        ];
    }
}
