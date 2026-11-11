<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'anio' => $this->anio,
            'sentido' => $this->sentido?->value ?? $this->sentido,
            'tipo_correspondencia' => $this->tipo_correspondencia?->value ?? $this->tipo_correspondencia,
            'fecha_documento' => $this->fecha_documento?->toDateString(),
            'fecha_recepcion' => $this->fecha_recepcion?->toDateString(),
            'oficio_original' => $this->oficio_original,
            'remitente' => $this->remitente,
            'dependencia' => $this->dependencia,
            'cargo_remitente' => $this->cargo_remitente,
            'asunto' => $this->asunto,
            'resumen' => $this->resumen,
            'observaciones' => $this->observaciones,
            'anexos' => $this->anexos,
            'canal_recepcion' => $this->canal_recepcion,
            'prioridad' => $this->prioridad?->value ?? $this->prioridad,
            'plazo' => $this->plazo?->toDateString(),
            'estado' => $this->estado?->value ?? $this->estado,
            'area_id' => $this->area_id,
            'datos_extra' => $this->datos_extra,
            'area' => new AreaResource($this->whenLoaded('area')),
            'user' => new UserResource($this->whenLoaded('user')),
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
