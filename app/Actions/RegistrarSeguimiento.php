<?php

namespace App\Actions;

use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class RegistrarSeguimiento
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Registra una acción de seguimiento del turno (RF-26) y avanza el estado
     * de pendiente a en trámite (RF-24).
     */
    public function handle(Turno $turno, string $notas, User $usuario, ?Request $request = null): Turno
    {
        abort_unless($turno->estaAbierto(), 409, 'El turno ya no está abierto.');

        $old = $turno->toArray();

        $turno->movimientos()->create([
            'tipo' => TipoMovimiento::Seguimiento,
            'usuario_origen_id' => $usuario->id,
            'notas' => $notas,
            'fecha' => now(),
        ]);

        if ($turno->estado === EstadoTurno::Pendiente) {
            $turno->update(['estado' => EstadoTurno::EnTramite]);
        }

        $this->auditoria->registrar(
            $usuario,
            'turno.seguimiento',
            $turno,
            $old,
            $turno->fresh()->toArray(),
            $request,
        );

        return $turno->fresh();
    }
}
