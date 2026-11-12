<?php

namespace App\Actions;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Models\Document;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditService;
use App\Services\VolanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnarDocumento
{
    public function __construct(
        private AuditService $auditoria,
        private VolanteService $volantes,
    ) {}

    /**
     * Turna un documento a una o varias áreas en un mismo acto (RF-11, RF-12,
     * RF-14, RF-17, RF-19, RF-20). Cada turno nace con su movimiento inicial
     * y estado pendiente, con seguimiento independiente (RN-05).
     *
     * @param  array<int, array<string, mixed>>  $turnos
     * @return Collection<int, Turno>
     */
    public function handle(Document $documento, array $turnos, User $usuario, ?Request $request = null): Collection
    {
        $esOficialia = $usuario->hasPermission('documents.create');

        if (! $esOficialia) {
            $turnoOrigen = $documento->turnos()
                ->where('responsable_id', $usuario->id)
                ->whereIn('estado', [EstadoTurno::Pendiente->value, EstadoTurno::EnTramite->value])
                ->latest('id')
                ->first();

            abort_unless(
                $turnoOrigen !== null,
                403,
                'Solo Oficialía puede turnar inicialmente; para un turnado interno debe ser responsable de un turno activo del documento.'
            );
        }

        $creados = DB::transaction(function () use ($documento, $turnos, $usuario, $request, $esOficialia): Collection {
            $primerTurno = ! $documento->turnos()->exists();
            $origen = $esOficialia ? null : $documento->turnos()
                ->where('responsable_id', $usuario->id)
                ->whereIn('estado', [EstadoTurno::Pendiente->value, EstadoTurno::EnTramite->value])
                ->latest('id')
                ->first();

            $creados = collect();

            foreach ($turnos as $datos) {
                $turno = Turno::create([
                    'document_id' => $documento->id,
                    'area_destino_id' => $datos['area_id'],
                    'tipo_asignacion' => $datos['tipo_asignacion'],
                    'estado' => EstadoTurno::Pendiente,
                    'responsable_id' => $datos['responsable_id'] ?? null,
                    'instrucciones' => $datos['instrucciones'] ?? null,
                    'plazo' => $datos['plazo'] ?? null,
                    'es_turnado_inicial' => $primerTurno,
                    'turno_padre_id' => $origen?->id,
                    'turnado_por_id' => $usuario->id,
                ]);

                $movimiento = $turno->movimientos()->create([
                    'tipo' => TipoMovimiento::Turnado,
                    'usuario_origen_id' => $usuario->id,
                    'notas' => $datos['instrucciones'] ?? null,
                    'fecha' => now(),
                    // La entrega física queda registrada antes del acuse digital (RF-47).
                    'acuse_pendiente' => true,
                ]);

                $this->auditoria->registrar(
                    $usuario,
                    'turno.creado',
                    $turno,
                    null,
                    $turno->toArray(),
                    $request,
                );

                $creados->push($turno->setRelation('movimientoInicial', $movimiento));
            }

            if ($documento->estado === EstadoDocumento::Registrado) {
                $documento->update(['estado' => EstadoDocumento::EnTramite]);
            }

            return $creados;
        });

        // Volante automático por turno: recepción inicial o turnado interno (RF-30, RF-35).
        foreach ($creados as $turno) {
            try {
                $this->volantes->generar($turno, $usuario, $turno->getRelation('movimientoInicial'), $request);
            } catch (Throwable $e) {
                Log::error('No se pudo generar el volante del turno '.$turno->id.': '.$e->getMessage());
            }
        }

        return $creados;
    }
}
