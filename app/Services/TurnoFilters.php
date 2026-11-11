<?php

namespace App\Services;

use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros de la bandeja de turnos (RF-28, RF-53), compartidos por el
 * listado y las exportaciones (RF-64).
 */
class TurnoFilters
{
    public function aplicar(Builder $query, Request $request): Builder
    {
        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('area_id')) {
            $query->where('area_destino_id', $request->integer('area_id'));
        }

        if ($request->filled('tipo_asignacion')) {
            $query->where('tipo_asignacion', $request->string('tipo_asignacion'));
        }

        if ($request->filled('vencido')) {
            $query->where('tipo_asignacion', 'atencion')
                ->whereNotNull('plazo')
                ->whereDate('plazo', '<', today())
                ->whereIn('estado', [EstadoTurno::Pendiente->value, EstadoTurno::EnTramite->value]);
        }

        // Filtrado de entregas con acuse pendiente de digitalizar (RF-53).
        if ($request->filled('acuse_pendiente')) {
            $query->whereHas('movimientos', fn ($q) => $q
                ->where('tipo', TipoMovimiento::Turnado)
                ->where('acuse_pendiente', true));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->string('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->string('fecha_hasta'));
        }

        if ($request->filled('responsable')) {
            $responsable = $request->string('responsable')->toString();

            if ($responsable === 'me') {
                $query->where('responsable_id', $request->user()->id);
            } else {
                $query->where('responsable_id', (int) $responsable);
            }
        }

        if ($request->filled('sin_responsable')) {
            $query->whereNull('responsable_id');
        }

        return $query;
    }

    /**
     * @return Builder<Turno>
     */
    public function construir(Request $request): Builder
    {
        return $this->aplicar(
            Turno::query()->with([
                'document:id,folio,asunto',
                'areaDestino:id,nombre,siglas',
                'responsable:id,name',
            ]),
            $request,
        );
    }
}
