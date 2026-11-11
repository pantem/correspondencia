<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Area::query()->orderBy('nombre');

        if (! $request->boolean('todas')) {
            $query->where('activo', true);
        }

        return AreaResource::collection($query->get());
    }

    public function store(StoreAreaRequest $request): AreaResource
    {
        $area = Area::create($request->validated());

        $this->auditoria->registrar($request->user(), 'area.creada', $area, null, $area->toArray(), $request);

        return new AreaResource($area);
    }

    public function update(UpdateAreaRequest $request, Area $area): AreaResource
    {
        $old = $area->toArray();

        $area->update($request->validated());

        $this->auditoria->registrar($request->user(), 'area.actualizada', $area, $old, $area->toArray(), $request);

        return new AreaResource($area);
    }
}
