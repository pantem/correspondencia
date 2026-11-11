<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private AuditService $auditoria) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->with('roles:id,name,slug')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
        );
    }

    public function roles(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->orderBy('name')->get());
    }

    /**
     * Catálogo mínimo de usuarios activos para selects (reasignación RF-16).
     */
    public function usuarios(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'activo'])
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'activo' => $request->boolean('activo', true),
        ]);

        $user->roles()->sync($request->input('role_ids'));

        $this->auditoria->registrar($request->user(), 'usuario.creado', $user, null, $user->toArray(), $request);

        return response()->json([
            'data' => new UserResource($user->load('roles:id,name,slug')),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $old = $user->toArray();

        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower($data['email']);
        }

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        $user->update($data);

        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        $this->auditoria->registrar($request->user(), 'usuario.actualizado', $user, $old, $user->toArray(), $request);

        return new UserResource($user->fresh()->load('roles:id,name,slug'));
    }
}
