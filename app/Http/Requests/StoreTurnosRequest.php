<?php

namespace App\Http\Requests;

use App\Enums\TipoAsignacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTurnosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.turn') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'turnos' => ['required', 'array', 'min:1'],
            'turnos.*.area_id' => ['required', 'integer', 'exists:areas,id'],
            'turnos.*.tipo_asignacion' => ['required', Rule::enum(TipoAsignacion::class)],
            'turnos.*.instrucciones' => ['nullable', 'string', 'max:5000'],
            'turnos.*.plazo' => ['nullable', 'date', 'after_or_equal:today'],
            'turnos.*.responsable_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turnos.min' => 'Debe indicar al menos un área destinataria.',
        ];
    }
}
