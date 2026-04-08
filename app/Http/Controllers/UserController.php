<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service)
    {
    }

    // ── Own profile ───────────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        // role.permissions already loaded by BindTenantScope
        return response()->json(new UserResource(
            $request->user()->loadMissing('customer')
        ));
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return response()->json(new UserResource($request->user()->fresh()));
    }

    public function myCustomer(Request $request): JsonResponse
    {
        if ($request->user()->isPlatformUser()) {
            return response()->json(['message' => 'Platform users have no customer.'], 403);
        }

        $customer = $request->user()->customer()->with('wallet', 'locations', 'ports')->firstOrFail();

        return response()->json(new CustomerResource($customer));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::exact('role_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('mobile', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedIncludes(['role', 'customer'])
            ->allowedSorts(['name', 'email', 'created_at', 'last_login_at'])
            ->defaultSort('-created_at')
            ->paginate($request->query('per_page', 20))
            ->appends($request->query());

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->service->store($request->validated(), $request->user());

        return response()->json(new UserResource($user), 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json(new UserResource($user->loadMissing('role.permissions')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->service->update($user, $request->validated());

        return response()->json(new UserResource($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->service->delete($user);

        return response()->json(['message' => 'User deleted.']);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $this->authorize('toggleActive', $user);

        $user = $this->service->toggleActive($user);

        return response()->json(new UserResource($user));
    }
}
