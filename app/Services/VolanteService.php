<?php

namespace App\Services;

use App\Enums\OrigenVolante;
use App\Models\Movimiento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Volante;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VolanteService
{
    public function __construct(private AuditService $auditoria) {}

    /**
     * Genera el volante PDF de un turno a partir de los datos registrados
     * (RF-30…RF-37): conserva el folio principal (RF-31), incluye QR y todos
     * los datos del RF-32, versiona por turno (RF-36) y marca el origen
     * recepción inicial vs turnado interno (RF-37).
     */
    public function generar(Turno $turno, User $usuario, ?Movimiento $movimiento = null, ?Request $request = null): Volante
    {
        $turno->loadMissing([
            'document:id,folio,asunto,sentido,fecha_documento,fecha_recepcion,oficio_original,remitente,dependencia,observaciones,estado,prioridad,plazo,area_id,user_id,anio',
            'document.area:id,nombre,siglas',
            'areaDestino:id,nombre,siglas',
            'responsable:id,name',
            'turnadoPor:id,name',
        ]);

        $movimiento ??= $turno->movimientos()->latest('id')->first();

        $version = ((int) Volante::where('turno_id', $turno->id)->max('version')) + 1;
        $origen = $turno->es_turnado_inicial
            ? OrigenVolante::RecepcionInicial
            : OrigenVolante::TurnadoInterno;

        $qr = (new Builder(
            writer: new PngWriter,
            data: url('/documentos/'.$turno->document->folio),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 130,
            margin: 4,
        ))->build();

        $html = view('volantes.volante', [
            'turno' => $turno,
            'documento' => $turno->document,
            'movimiento' => $movimiento,
            'origen' => $origen,
            'version' => $version,
            'emitidoPor' => $usuario,
            'emitidoAt' => now(),
            'qrImagen' => 'data:image/png;base64,'.base64_encode($qr->getString()),
        ])->render();

        $contenido = Pdf::loadHtml($html)->setPaper('letter')->output();

        $ruta = 'volantes/turno-'.$turno->id.'/volante-'.$version.'.pdf';
        Storage::disk(config('filesystems.default'))->put($ruta, $contenido);

        $volante = Volante::create([
            'turno_id' => $turno->id,
            'movimiento_id' => $movimiento?->id,
            'version' => $version,
            'origen' => $origen,
            'path' => $ruta,
            'sha256' => hash('sha256', $contenido),
            'emitido_por' => $usuario->id,
            'emitido_at' => now(),
        ]);

        $this->auditoria->registrar(
            $usuario,
            'volante.emitido',
            $volante,
            null,
            $volante->toArray(),
            $request,
        );

        return $volante;
    }
}
