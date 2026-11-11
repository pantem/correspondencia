<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SeguimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('turns.respond') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notas' => ['required', 'string', 'max:5000'],
        ];
    }
}
