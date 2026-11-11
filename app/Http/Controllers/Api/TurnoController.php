<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReasignarTurno;
use App\Actions\RegistrarSeguimiento;
use App\Actions\ResolverTurno;
use App\Actions\TurnarDocumento;
use App\Enums\EstadoTurno;
use App\Enums\TipoMovimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelarTurnoRequest;
use App\Http\Requests\ReasignarTurnoRequest;
use App\Http\Requests\SeguimientoRequest;
use App\Http\Requests\StoreTurnosRequest;
use App\Http\Requests\UpdateTurnoRequest;
use App\Http\Resources\TurnoResource;
use App\Models\Area;
use App\Models\Document;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TurnoFilters;
use App\Services\VolanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnoController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Todos los turnos de un documento con seguimiento independiente (RF-17, RF-18).
     */
    public function turnosDocumento(Request $request, Document $document): AnonymousResourceCollection
    {
        $this->authorize('documents.view');

        return TurnoResource::collection(
            $document->turnos()
                ->with(['areaDestino:id,nombre,siglas', 'responsable:id,name', 'turnadoPor:id,name'])
                ->withExists([
                    'movimientos as entrega_acuse_pendiente_count' => fn ($q) => $q
                        ->where('tipo', TipoMovimiento::Turnado)
                        ->where('acuse_pendiente', true),
                ])
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * Turnado inicial o interno a una o varias áreas en un mismo acto (RF-11, RF-12, RF-20).
     */
    public function store(StoreTurnosRequest $request, Document $document, TurnarDocumento $turnar): JsonResponse
    {
        $turnos = $turnar->handle($document, $request->validated('turnos'), $request->user(), $request);

        $creados = Turno::with(['areaDestino:id,nombre,siglas', 'responsable:id,name', 'turnadoPor:id,name'])
            ->findMany($turnos->pluck('id'))
            ->sortBy('id')
            ->values();

        return TurnoResource::collection($creados)->response()->setStatusCode(201);
    }

    /**
     * Bandeja de turnos con filtros de estado, área, tipo, responsable y
     * acuses pendientes (RF-28, RF-53, RF-62).
     */
    public function index(Request $request, TurnoFilters $filtros): AnonymousResourceCollection
    {
        $this->authorize('documents.view');

        $query = $filtros->construir($request)
            ->withExists([
                'movimientos as entrega_acuse_pendiente_count' => fn ($q) => $q
                    ->where('tipo', TipoMovimiento::Turnado)
                    ->where('acuse_pendiente', true),
            ])
            ->latest('id');

        return TurnoResource::collection(
            $query->paginate(15)->withQueryString()
        );
    }

    public function show(Turno $turno): TurnoResource
    {
        $this->authorize('documents.view');

        return new TurnoResource(
            $turno->load([
                'document:id,folio,asunto',
                'areaDestino:id,nombre,siglas',
                'responsable:id,name',
                'turnadoPor:id,name',
                'resueltoPor:id,name',
                'movimientos.usuarioOrigen:id,name',
                'movimientos.responsableAnterior:id,name',
                'movimientos.archivos',
            ])
        );
    }

    /**
     * Corrección del destinatario de un turno pendiente sin recapturar los
     * datos generales (RF-39, RF-40, RF-41): registra movimiento de corrección,
     * auditoría con valores anterior/nuevo y una nueva versión de volante
     * (RF-35). Los turnos resueltos quedan excluidos (RF-43).
     */
    public function update(UpdateTurnoRequest $request, Turno $turno, VolanteService $volantes): TurnoResource
    {
        abort_unless($turno->estado === EstadoTurno::Pendiente, 409, 'Solo se pueden editar turnos pendientes.');

        $old = $turno->toArray();

        $turno->update($request->validated());

        $destinatarioCorregido = ($old['area_destino_id'] ?? null) !== $turno->area_destino_id
            || ($old['tipo_asignacion'] ?? null) !== $turno->tipo_asignacion->value
            || ($old['responsable_id'] ?? null) !== $turno->responsable_id;

        if ($destinatarioCorregido) {
            $turno->movimientos()->create([
                'tipo' => TipoMovimiento::Correccion,
                'usuario_origen_id' => $request->user()->id,
                'responsable_anterior_id' => ($old['responsable_id'] ?? null) !== $turno->responsable_id
                    ? ($old['responsable_id'] ?? null)
                    : null,
                'notas' => sprintf(
                    'Corrección de destinatario: área %s → %s · tipo %s → %s · responsable %s → %s',
                    Area::find($old['area_destino_id'] ?? null)?->nombre ?? '—',
                    $turno->areaDestino?->nombre ?? '—',
                    $old['tipo_asignacion'] ?? '—',
                    $turno->tipo_asignacion->value,
                    $old['responsable_id'] ? (User::find($old['responsable_id'])?->name ?? '—') : 'Sin asignar',
                    $turno->responsable?->name ?? 'Sin asignar',
                ),
                'fecha' => now(),
            ]);

            try {
                $volantes->generar($turno->fresh(), $request->user(), null, $request);
            } catch (Throwable $e) {
                Log::error('No se pudo regenerar el volante del turno '.$turno->id.': '.$e->getMessage());
            }
        }

        $this->auditoria->registrar(
            $request->user(),
            $destinatarioCorregido ? 'turno.corregido' : 'turno.actualizado',
            $turno,
            $old,
            $turno->toArray(),
            $request,
        );

        return new TurnoResource(
            $turno->fresh()->load(['areaDestino:id,nombre,siglas', 'responsable:id,name'])
        );
    }

    /**
     * Reasignación con historial de responsables anteriores (RF-16, RN-07).
     */
    public function reasignar(ReasignarTurnoRequest $request, Turno $turno, ReasignarTurno $reasignar): TurnoResource
    {
        $user = $request->user();

        if (! $user->can('turns.update')) {
            abort_unless(
                $turno->responsable_id === $user->id,
                403,
                'Solo el responsable vigente o un usuario con permiso de gestión puede reasignar.'
            );
        }

        $turno = $reasignar->handle(
            $turno,
            $request->integer('responsable_id'),
            $request->input('notas'),
            $user,
            $request,
        );

        return new TurnoResource(
            $turno->load(['areaDestino:id,nombre,siglas', 'responsable:id,name'])
        );
    }

    /**
     * Acción de seguimiento; avanza pendiente → en trámite (RF-24, RF-26).
     */
    public function seguimiento(SeguimientoRequest $request, Turno $turno, RegistrarSeguimiento $accion): TurnoResource
    {
        $turno = $accion->handle($turno, $request->validated('notas'), $request->user(), $request);

        return new TurnoResource(
            $turno->load(['areaDestino:id,nombre,siglas', 'responsable:id,name'])
        );
    }

    /**
     * Resolución solo por responsable vigente (RF-25, RN-08, RN-09).
     */
    public function resolver(Request $request, Turno $turno, ResolverTurno $resolver): TurnoResource
    {
        $this->authorize('turns.respond');

        $turno = $resolver->handle($turno, $request->user(), $request);

        return new TurnoResource(
            $turno->load(['areaDestino:id,nombre,siglas', 'responsable:id,name', 'resueltoPor:id,name'])
        );
    }

    /**
     * Cancelación de un turno abierto con motivo en el historial (RF-24).
     */
    public function cancelar(CancelarTurnoRequest $request, Turno $turno): TurnoResource
    {
        abort_unless($turno->estaAbierto(), 409, 'El turno ya no está abierto.');

        $old = $turno->toArray();

        $turno->update(['estado' => EstadoTurno::Cancelado]);

        $turno->movimientos()->create([
            'tipo' => TipoMovimiento::Cancelacion,
            'usuario_origen_id' => $request->user()->id,
            'notas' => $request->validated('motivo'),
            'fecha' => now(),
        ]);

        $this->auditoria->registrar(
            $request->user(),
            'turno.cancelado',
            $turno,
            $old,
            $turno->toArray(),
            $request,
        );

        return new TurnoResource(
            $turno->fresh()->load(['areaDestino:id,nombre,siglas', 'responsable:id,name'])
        );
    }
}
