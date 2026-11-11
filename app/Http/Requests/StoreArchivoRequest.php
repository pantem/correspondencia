<?php

namespace App\Http\Requests;

use App\Enums\TipoArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArchivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'tipo' => ['sometimes', Rule::enum(TipoArchivo::class)],
        ];
    }
}
