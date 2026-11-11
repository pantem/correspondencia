<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCargoRequest;
use App\Http\Requests\UpdateCargoRequest;
use App\Http\Resources\CargoResource;
use App\Models\Cargo;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CargoController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Cargo::query()->orderBy('nombre');

        if (! $request->boolean('todas')) {
            $query->where('activo', true);
        }

        return CargoResource::collection($query->get());
    }

    public function store(StoreCargoRequest $request): CargoResource
    {
        $cargo = Cargo::create($request->validated());

        $this->auditoria->registrar($request->user(), 'cargo.creado', $cargo, null, $cargo->toArray(), $request);

        return new CargoResource($cargo);
    }

    public function update(UpdateCargoRequest $request, Cargo $cargo): CargoResource
    {
        $old = $cargo->toArray();

        $cargo->update($request->validated());

        $this->auditoria->registrar($request->user(), 'cargo.actualizado', $cargo, $old, $cargo->toArray(), $request);

        return new CargoResource($cargo);
    }
}
