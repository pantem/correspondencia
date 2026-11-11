<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'activo' => $this->activo,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->permissionsList(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
