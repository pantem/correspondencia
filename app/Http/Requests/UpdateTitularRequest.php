<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTitularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.catalogs') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'cargo_id' => ['sometimes', 'integer', 'exists:cargos,id'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['nullable', 'date', 'after:desde'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
