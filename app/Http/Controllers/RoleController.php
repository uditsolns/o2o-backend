<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    public function index(): ResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedIncludes('permissions')
            ->allowedFilters(['name'])
            ->when(request()->user()->isClientUser(),
                fn($q) => $q->whereIn('name', ['operations_executive', 'driver']))
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Role $role): JsonResource
    {
        return new RoleResource($role->load('permissions'));
    }

    public function permissions(): JsonResponse
    {
        return response()->json(Permission::orderBy('name')->pluck('name'));
    }
}
