<?php

namespace App\Actions;

use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ResolverTurno
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Marca un turno como resuelto. Regla RN-08: solo el responsable vigente
     * puede resolver; RN-09: no cierra los demás turnos del documento;
     * RF-29: la resolución queda en el historial (movimiento).
     */
    public function handle(Turno $turno, User $usuario, ?Request $request = null): Turno
    {
        abort_unless(
            $turno->responsable_id === $usuario->id,
            403,
            'Solo la persona con responsabilidad vigente puede marcar este turno como resuelto.'
        );

        abort_unless($turno->estaAbierto(), 409, 'El turno ya no está abierto.');

        $old = $turno->toArray();

        $turno->update([
            'estado' => EstadoTurno::Resuelto,
            'resuelto_por_id' => $usuario->id,
            'resuelto_at' => now(),
        ]);

        $turno->movimientos()->create([
            'tipo' => TipoMovimiento::Resolucion,
            'usuario_origen_id' => $usuario->id,
            'fecha' => now(),
        ]);

        $this->auditoria->registrar(
            $usuario,
            'turno.resuelto',
            $turno,
            $old,
            $turno->toArray(),
            $request,
        );

        return $turno;
    }
}
