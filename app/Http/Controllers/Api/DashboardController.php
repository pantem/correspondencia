<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Http\Controllers\Controller;
use App\Http\Resources\TurnoResource;
use App\Models\Document;
use App\Models\Movimiento;
use App\Models\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Indicadores de la portada (RF-61): KPIs, pendientes por área y
     * mis turnos pendientes.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('documents.view');

        $abiertos = [EstadoTurno::Pendiente->value, EstadoTurno::EnTramite->value];

        $kpis = [
            'documentos' => Document::count(),
            'turnos_pendientes' => Turno::where('estado', EstadoTurno::Pendiente->value)->count(),
            'turnos_en_tramite' => Turno::where('estado', EstadoTurno::EnTramite->value)->count(),
            'turnos_resueltos' => Turno::where('estado', EstadoTurno::Resuelto->value)->count(),
            'acuses_pendientes' => Movimiento::where('tipo', TipoMovimiento::Turnado->value)
                ->where('acuse_pendiente', true)
                ->count(),
            'turnos_vencidos' => Turno::where('tipo_asignacion', 'atencion')
                ->whereNotNull('plazo')
                ->whereDate('plazo', '<', today())
                ->whereIn('estado', $abiertos)
                ->count(),
        ];

        $pendientesPorArea = Turno::whereIn('estado', $abiertos)
            ->join('areas', 'areas.id', '=', 'turnos.area_destino_id')
            ->selectRaw('areas.nombre as area, count(*) as total')
            ->groupBy('areas.nombre')
            ->orderByDesc('total')
            ->get();

        $misTurnos = Turno::with(['document:id,folio,asunto', 'areaDestino:id,nombre,siglas'])
            ->withExists([
                'movimientos as entrega_acuse_pendiente_count' => fn ($q) => $q
                    ->where('tipo', TipoMovimiento::Turnado)
                    ->where('acuse_pendiente', true),
            ])
            ->where('responsable_id', $request->user()->id)
            ->whereIn('estado', $abiertos)
            ->orderByRaw('plazo is null, plazo asc')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'kpis' => $kpis,
                'pendientes_por_area' => $pendientesPorArea,
                'mis_turnos' => TurnoResource::collection($misTurnos),
            ],
        ]);
    }
}
