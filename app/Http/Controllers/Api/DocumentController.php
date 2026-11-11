<?php

namespace App\Http\Controllers\Api;

use App\Actions\CrearDocumento;
use App\Enums\TipoArchivo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArchivoRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\ArchivoResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\MovimientoResource;
use App\Models\Archivo;
use App\Models\Document;
use App\Models\Movimiento;
use App\Services\AuditService;
use App\Services\DocumentFilters;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Listado y búsqueda multi-criterio (RF-60).
     */
    public function index(Request $request, DocumentFilters $filtros): AnonymousResourceCollection
    {
        $query = $filtros->construir($request)
            ->latest('created_at')
            ->latest('id');

        return DocumentResource::collection(
            $query->paginate(15)->withQueryString()
        );
    }

    /**
     * Historial cronológico de movimientos/turnos del documento (RF-61).
     */
    public function historial(Request $request, Document $document): AnonymousResourceCollection
    {
        $this->authorize('documents.view');

        $movimientos = Movimiento::query()
            ->whereIn('turno_id', $document->turnos()->select('id'))
            ->with([
                'usuarioOrigen:id,name',
                'responsableAnterior:id,name',
                'archivos.uploader:id,name',
                'turno.areaDestino:id,nombre,siglas',
                'turno.document:id,folio,asunto',
            ])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return MovimientoResource::collection($movimientos);
    }

    public function store(StoreDocumentRequest $request, CrearDocumento $crear): DocumentResource
    {
        $documento = $crear->handle($request->validated(), $request->user(), $request);

        return new DocumentResource(
            $documento->load(['area:id,nombre,siglas', 'user:id,name', 'archivos'])
        );
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource(
            $document->load(['area:id,nombre,siglas', 'user:id,name', 'archivos', 'archivos.uploader:id,name'])
        );
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $old = $document->toArray();

        $document->update($request->validated());

        $this->auditoria->registrar(
            $request->user(),
            'documento.actualizado',
            $document,
            $old,
            $document->toArray(),
            $request,
        );

        return new DocumentResource(
            $document->fresh()->load(['area:id,nombre,siglas', 'user:id,name', 'archivos'])
        );
    }

    /**
     * Asocia un documento digitalizado al registro (RF-03).
     */
    public function storeArchivo(StoreArchivoRequest $request, Document $document): ArchivoResource
    {
        $file = $request->file('archivo');
        $hash = hash_file('sha256', $file->getRealPath());
        $ruta = $file->store('documentos/'.$document->folio, config('filesystems.default'));

        $archivo = $document->archivos()->create([
            'tipo' => $request->input('tipo', TipoArchivo::Original->value),
            'original_name' => $file->getClientOriginalName(),
            'path' => $ruta,
            'sha256' => $hash,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->auditoria->registrar(
            $request->user(),
            'archivo.cargado',
            $archivo,
            null,
            $archivo->toArray(),
            $request,
        );

        return new ArchivoResource($archivo);
    }

    public function showArchivo(Request $request, Document $document, Archivo $archivo): BinaryFileResponse
    {
        abort_unless($archivo->document_id === $document->id, 404);

        $path = Storage::disk(config('filesystems.default'))->path($archivo->path);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$archivo->original_name.'"',
        ]);
    }
}
