<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'turno_id' => $this->turno_id,
            'turno' => new TurnoResource($this->whenLoaded('turno')),
            'tipo' => $this->tipo?->value ?? $this->tipo,
            'usuario_origen' => new UserResource($this->whenLoaded('usuarioOrigen')),
            'responsable_anterior' => new UserResource($this->whenLoaded('responsableAnterior')),
            'notas' => $this->notas,
            'fecha' => $this->fecha,
            'acuse_pendiente' => $this->acuse_pendiente,
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
            'created_at' => $this->created_at,
        ];
    }
}
