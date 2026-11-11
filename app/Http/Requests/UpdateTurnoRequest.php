<?php

namespace App\Http\Requests;

use App\Enums\TipoAsignacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('turns.update') ?? false;
    }

    /**
     * Corrección del destinatario de un turno pendiente sin recapturar los
     * datos generales del documento (RF-39).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_destino_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'tipo_asignacion' => ['sometimes', Rule::enum(TipoAsignacion::class)],
            'instrucciones' => ['nullable', 'string', 'max:5000'],
            'plazo' => ['nullable', 'date'],
        ];
    }
}
