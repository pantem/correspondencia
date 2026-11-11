<?php

namespace App\Actions;

use App\Enums\TipoMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ReasignarTurno
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Reasigna el responsable de un turno conservando el historial de
     * responsables anteriores (RF-16, RN-07).
     */
    public function handle(Turno $turno, int $nuevoResponsableId, string $notas, User $usuario, ?Request $request = null): Turno
    {
        abort_unless($turno->estaAbierto(), 409, 'El turno ya no está abierto.');
        abort_unless($turno->responsable_id !== $nuevoResponsableId, 422, 'El nuevo responsable ya es el responsable vigente.');

        $old = $turno->toArray();

        $turno->update(['responsable_id' => $nuevoResponsableId]);

        $turno->movimientos()->create([
            'tipo' => TipoMovimiento::Reasignacion,
            'usuario_origen_id' => $usuario->id,
            'responsable_anterior_id' => $old['responsable_id'] ?? null,
            'notas' => $notas,
            'fecha' => now(),
        ]);

        $this->auditoria->registrar(
            $usuario,
            'turno.reasignado',
            $turno,
            $old,
            $turno->toArray(),
            $request,
        );

        return $turno->fresh();
    }
}
