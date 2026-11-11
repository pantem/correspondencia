<?php

namespace App\Http\Resources;

use App\Enums\TipoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'folio' => $this->when(
                fn () => $this->relationLoaded('document'),
                fn () => $this->document->folio,
            ),
            'asunto' => $this->when(
                fn () => $this->relationLoaded('document'),
                fn () => $this->document->asunto,
            ),
            'area_destino_id' => $this->area_destino_id,
            'area_destino' => new AreaResource($this->whenLoaded('areaDestino')),
            'tipo_asignacion' => $this->tipo_asignacion?->value ?? $this->tipo_asignacion,
            'estado' => $this->estado?->value ?? $this->estado,
            'responsable' => new UserResource($this->whenLoaded('responsable')),
            'instrucciones' => $this->instrucciones,
            'plazo' => $this->plazo?->toDateString(),
            'vencido' => $this->vencido(),
            // Entrega física sin acuse digital cargado (RF-47, RF-48).
            'acuse_pendiente' => $this->when(
                fn () => $this->resource->hasAttribute('entrega_acuse_pendiente_count')
                    || $this->resource->relationLoaded('movimientos'),
                fn () => $this->resource->hasAttribute('entrega_acuse_pendiente_count')
                    ? $this->resource->entrega_acuse_pendiente_count > 0
                    : $this->resource->movimientos->contains(
                        fn ($m) => $m->tipo === TipoMovimiento::Turnado && $m->acuse_pendiente
                    ),
            ),
            'es_turnado_inicial' => $this->es_turnado_inicial,
            'turno_padre_id' => $this->turno_padre_id,
            'turnado_por' => new UserResource($this->whenLoaded('turnadoPor')),
            'resuelto_por' => new UserResource($this->whenLoaded('resueltoPor')),
            'resuelto_at' => $this->resuelto_at,
            'movimientos' => MovimientoResource::collection($this->whenLoaded('movimientos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
