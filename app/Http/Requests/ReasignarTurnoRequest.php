<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReasignarTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('turns.update') || $user->can('turns.respond'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'responsable_id' => ['required', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
