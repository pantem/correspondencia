<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TitularResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'area_id' => $this->area_id,
            'cargo_id' => $this->cargo_id,
            'desde' => $this->desde?->toDateString(),
            'hasta' => $this->hasta?->toDateString(),
            'activo' => $this->activo,
            'area' => new AreaResource($this->whenLoaded('area')),
            'cargo' => new CargoResource($this->whenLoaded('cargo')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
