<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTitularRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after:desde'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
