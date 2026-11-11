<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Registra una operación en la bitácora (RF-57, RF-63).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function registrar(
        ?User $user,
        string $accion,
        Model $entidad,
        ?array $old = null,
        ?array $new = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'accion' => $accion,
            'auditable_type' => $entidad->getMorphClass(),
            'auditable_id' => $entidad->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
        ]);
    }
}
