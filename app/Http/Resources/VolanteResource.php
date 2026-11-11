<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'turno_id' => $this->turno_id,
            'movimiento_id' => $this->movimiento_id,
            'version' => $this->version,
            'origen' => $this->origen?->value ?? $this->origen,
            'sha256' => $this->sha256,
            'emitido_por' => new UserResource($this->whenLoaded('emisor')),
            'emitido_at' => $this->emitido_at,
            'url_pdf' => route('volantes.pdf', $this->id),
            'turno' => new TurnoResource($this->whenLoaded('turno')),
            'created_at' => $this->created_at,
        ];
    }
}
