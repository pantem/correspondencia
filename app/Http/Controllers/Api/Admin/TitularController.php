<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTitularRequest;
use App\Http\Requests\UpdateTitularRequest;
use App\Http\Resources\TitularResource;
use App\Models\Titular;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TitularController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Titular::query()
            ->with(['area:id,nombre,siglas', 'cargo:id,nombre'])
            ->orderBy('nombre');

        if (! $request->boolean('todos')) {
            $query->where('activo', true);
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->integer('area_id'));
        }

        return TitularResource::collection($query->get());
    }

    public function store(StoreTitularRequest $request): TitularResource
    {
        $titular = Titular::create($request->validated());

        $this->auditoria->registrar($request->user(), 'titular.creado', $titular, null, $titular->toArray(), $request);

        return new TitularResource($titular->load(['area:id,nombre,siglas', 'cargo:id,nombre']));
    }

    public function update(UpdateTitularRequest $request, Titular $titular): TitularResource
    {
        $old = $titular->toArray();

        $titular->update($request->validated());

        $this->auditoria->registrar($request->user(), 'titular.actualizado', $titular, $old, $titular->toArray(), $request);

        return new TitularResource($titular->fresh()->load(['area:id,nombre,siglas', 'cargo:id,nombre']));
    }
}
