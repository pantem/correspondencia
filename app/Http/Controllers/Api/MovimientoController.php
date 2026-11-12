<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoArchivo;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubirAcuseRequest;
use App\Http\Resources\ArchivoResource;
use App\Models\Movimiento;
use App\Services\AuditService;

class MovimientoController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Carga el acuse digital (copia sellada) del movimiento que acredita la
     * entrega física (RF-47…RF-52). Cada acuse queda vinculado al movimiento,
     * versionado y con usuario/fecha de carga; nunca sustituye versiones
     * anteriores (RF-54).
     */
    public function subirAcuse(SubirAcuseRequest $request, Movimiento $movimiento): ArchivoResource
    {
        $file = $request->file('archivo');
        $hash = hash_file('sha256', $file->getRealPath());
        $documento = $movimiento->turno->document;

        $ruta = $file->store(
            'documentos/'.$documento->folio.'/acuses',
            config('filesystems.default'),
        );

        $version = $movimiento->archivos()
            ->where('tipo', TipoArchivo::Acuse)
            ->count() + 1;

        $archivo = $movimiento->archivos()->create([
            'document_id' => $documento->id,
            'tipo' => TipoArchivo::Acuse,
            'version' => $version,
            'original_name' => $file->getClientOriginalName(),
            'path' => $ruta,
            'sha256' => $hash,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $movimiento->update(['acuse_pendiente' => false]);

        $this->auditoria->registrar(
            $request->user(),
            'acuse.cargado',
            $archivo,
            null,
            $archivo->toArray(),
            $request,
        );

        return new ArchivoResource($archivo->load('uploader:id,name'));
    }

    public function subirResolucion(SubirAcuseRequest $request, Movimiento $movimiento): ArchivoResource
    {
        $file = $request->file('archivo');
        $hash = hash_file('sha256', $file->getRealPath());
        $documento = $movimiento->turno->document;

        $ruta = $file->store(
            'documentos/'.$documento->folio.'/resoluciones',
            config('filesystems.default'),
        );

        $archivo = $movimiento->archivos()->create([
            'document_id' => $documento->id,
            'tipo' => TipoArchivo::Resolucion,
            'original_name' => $file->getClientOriginalName(),
            'path' => $ruta,
            'sha256' => $hash,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->auditoria->registrar(
            $request->user(),
            'resolucion.cargada',
            $archivo,
            null,
            $archivo->toArray(),
            $request,
        );

        return new ArchivoResource($archivo->load('uploader:id,name'));
    }
}
