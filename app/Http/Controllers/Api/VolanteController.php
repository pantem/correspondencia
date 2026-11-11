<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VolanteResource;
use App\Models\Document;
use App\Models\Turno;
use App\Models\Volante;
use App\Services\VolanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolanteController extends Controller
{
    public function __construct(private VolanteService $volantes) {}

    /**
     * Historial de versiones de volantes de un turno (RF-36).
     */
    public function turno(Request $request, Turno $turno): AnonymousResourceCollection
    {
        $this->authorize('documents.view');

        return VolanteResource::collection(
            $turno->volantes()
                ->with(['emisor:id,name', 'turno.areaDestino:id,nombre,siglas', 'turno.document:id,folio,asunto'])
                ->orderByDesc('version')
                ->get()
        );
    }

    /**
     * Localizar los volantes de un documento por su folio principal (RF-38).
     */
    public function documento(Request $request, Document $document): AnonymousResourceCollection
    {
        $this->authorize('documents.view');

        return VolanteResource::collection(
            $document->volantes()
                ->with(['emisor:id,name', 'turno.areaDestino:id,nombre,siglas', 'turno.document:id,folio,asunto'])
                ->orderByDesc('id')
                ->get()
        );
    }

    /**
     * Genera una nueva versión del volante de un turno (RF-35, RF-36).
     */
    public function store(Request $request, Turno $turno): JsonResponse
    {
        abort_unless($turno->estaAbierto(), 409, 'Solo se pueden generar volantes de turnos abiertos.');

        $volante = $this->volantes->generar($turno, $request->user(), null, $request);

        return (new VolanteResource(
            $volante->load(['emisor:id,name', 'turno.areaDestino:id,nombre,siglas', 'turno.document:id,folio,asunto'])
        ))->response()->setStatusCode(201);
    }

    /**
     * Datos del volante para consulta en pantalla (RF-33, RF-34).
     */
    public function show(Volante $volante): VolanteResource
    {
        $this->authorize('documents.view');

        return new VolanteResource(
            $volante->load(['emisor:id,name', 'turno.areaDestino:id,nombre,siglas', 'turno.responsable:id,name', 'turno.document:id,folio,asunto'])
        );
    }

    /**
     * Descarga del PDF del volante (RF-33).
     */
    public function pdf(Request $request, Volante $volante): StreamedResponse
    {
        $this->authorize('documents.view');

        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($volante->path), 404);

        $folio = $volante->turno?->document?->folio ?? $volante->id;

        return $disk->response($volante->path, 'volante-'.$folio.'-v'.$volante->version.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="volante-'.$folio.'-v'.$volante->version.'.pdf"',
        ]);
    }
}
