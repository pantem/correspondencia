<?php

namespace App\Http\Requests;

use App\Enums\CanalRecepcion;
use App\Enums\EstadoDocumento;
use App\Enums\Prioridad;
use App\Enums\SentidoCorrespondencia;
use App\Enums\TipoCorrespondencia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sentido' => ['sometimes', Rule::enum(SentidoCorrespondencia::class)],
            'tipo_correspondencia' => ['sometimes', Rule::enum(TipoCorrespondencia::class)],
            'fecha_documento' => ['sometimes', 'date'],
            'fecha_recepcion' => ['nullable', 'date'],
            'oficio_original' => ['nullable', 'string', 'max:100'],
            'remitente' => ['nullable', 'string', 'max:255'],
            'dependencia' => ['nullable', 'string', 'max:255'],
            'cargo_remitente' => ['nullable', 'string', 'max:255'],
            'asunto' => ['sometimes', 'string', 'max:500'],
            'resumen' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'anexos' => ['nullable', 'string', 'max:2000'],
            'canal_recepcion' => ['nullable', Rule::enum(CanalRecepcion::class)],
            'prioridad' => ['sometimes', Rule::enum(Prioridad::class)],
            'plazo' => ['nullable', 'date'],
            'estado' => ['sometimes', Rule::enum(EstadoDocumento::class)],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'datos_extra' => ['nullable', 'array'],
        ];
    }
}
