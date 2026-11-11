<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentFilters;
use App\Services\TurnoFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Exportación CSV de correspondencia con los mismos filtros del
     * listado (RF-64). BOM UTF-8 para compatibilidad con Excel.
     */
    public function documentos(Request $request, DocumentFilters $filtros): StreamedResponse
    {
        $this->authorize('documents.view');

        $documentos = $filtros->construir($request)
            ->latest('created_at')
            ->latest('id')
            ->limit(10000)
            ->get();

        return $this->csv(
            'documentos-'.now()->format('Ymd-His').'.csv',
            ['Folio', 'Fecha', 'Sentido', 'Tipo de correspondencia', 'Remitente', 'Asunto', 'Estado', 'Area', 'Registrado por'],
            $documentos->map(fn ($d) => [
                $d->folio,
                optional($d->fecha_documento)->format('d/m/Y'),
                $d->sentido?->value,
                $d->tipo_correspondencia?->value,
                $d->remitente,
                $d->asunto,
                $d->estado?->value,
                $d->area?->nombre,
                $d->user?->name,
            ]),
        );
    }

    /**
     * Exportación CSV de turnos con los mismos filtros de la bandeja (RF-64).
     */
    public function turnos(Request $request, TurnoFilters $filtros): StreamedResponse
    {
        $this->authorize('documents.view');

        $turnos = $filtros->construir($request)
            ->with('turnadoPor:id,name')
            ->latest('id')
            ->limit(10000)
            ->get();

        return $this->csv(
            'turnos-'.now()->format('Ymd-His').'.csv',
            ['Turno', 'Documento', 'Asunto', 'Area destino', 'Tipo de asignacion', 'Responsable', 'Estado', 'Plazo', 'Turnado por', 'Fecha', 'Acuse pendiente'],
            $turnos->map(fn ($t) => [
                $t->id,
                $t->document?->folio,
                $t->document?->asunto,
                $t->areaDestino?->nombre,
                $t->tipo_asignacion?->value,
                $t->responsable?->name ?? 'Sin asignar',
                $t->estado?->value,
                optional($t->plazo)->format('d/m/Y'),
                $t->turnadoPor?->name,
                $t->created_at->format('d/m/Y H:i'),
                $t->entrega_acuse_pendiente_count > 0 ? 'Si' : 'No',
            ]),
        );
    }

    /**
     * @param  list<string>  $encabezados
     * @param  iterable<array<int, mixed>>  $filas
     */
    private function csv(string $nombre, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel reconozca los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $encabezados);

            foreach ($filas as $fila) {
                fputcsv($out, $fila);
            }

            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
