<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Búsqueda multi-criterio de correspondencia (RF-60), compartida por el
 * listado y las exportaciones (RF-64).
 */
class DocumentFilters
{
    public function aplicar(Builder $query, Request $request): Builder
    {
        if ($request->filled('folio')) {
            $query->where('folio', 'like', '%'.$request->string('folio').'%');
        }

        if ($request->filled('asunto')) {
            $query->where('asunto', 'like', '%'.$request->string('asunto').'%');
        }

        if ($request->filled('remitente')) {
            $query->where('remitente', 'like', '%'.$request->string('remitente').'%');
        }

        if ($request->filled('oficio')) {
            $query->where('oficio_original', 'like', '%'.$request->string('oficio').'%');
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->integer('area_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('sentido')) {
            $query->where('sentido', $request->string('sentido'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_documento', '>=', $request->string('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_documento', '<=', $request->string('fecha_hasta'));
        }

        return $query;
    }

    /**
     * @return Builder<Document>
     */
    public function construir(Request $request): Builder
    {
        return $this->aplicar(
            Document::query()->with(['area:id,nombre,siglas', 'user:id,name']),
            $request,
        );
    }
}
