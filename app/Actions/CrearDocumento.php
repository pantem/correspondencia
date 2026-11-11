<?php

namespace App\Actions;

use App\Models\Document;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FolioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrearDocumento
{
    public function __construct(
        private FolioService $folios,
        private AuditService $auditoria,
    ) {}

    /**
     * Registra un documento y le asigna folio único (RF-01, RF-02, RF-06).
     *
     * @param  array<string, mixed>  $datos
     */
    public function handle(array $datos, User $usuario, ?Request $request = null): Document
    {
        return DB::transaction(function () use ($datos, $usuario, $request): Document {
            $anio = now()->year;

            $datos['anio'] = $anio;
            $datos['folio'] = $this->folios->siguiente($anio);
            $datos['user_id'] = $usuario->id;

            $documento = Document::create($datos);

            $this->auditoria->registrar(
                $usuario,
                'documento.creado',
                $documento,
                null,
                $documento->toArray(),
                $request,
            );

            return $documento;
        });
    }
}
