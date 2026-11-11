<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArchivoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo?->value ?? $this->tipo,
            'version' => $this->version,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'sha256' => $this->sha256,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'movimiento_id' => $this->movimiento_id,
            'url' => route('documents.archivos.show', [$this->document?->folio, $this->id]),
            'created_at' => $this->created_at,
        ];
    }
}
