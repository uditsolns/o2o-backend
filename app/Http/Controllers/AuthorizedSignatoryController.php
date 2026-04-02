<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizedSignatoryRequest;
use App\Http\Resources\AuthorizedSignatoryResource;
use App\Models\AuthorizedSignatory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AuthorizedSignatoryController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', AuthorizedSignatory::class);

        return AuthorizedSignatoryResource::collection(AuthorizedSignatory::all());
    }

    public function store(AuthorizedSignatoryRequest $request)
    {
        $this->authorize('create', AuthorizedSignatory::class);

        return new AuthorizedSignatoryResource(AuthorizedSignatory::create($request->validated()));
    }

    public function show(AuthorizedSignatory $authorizedSignatory)
    {
        $this->authorize('view', $authorizedSignatory);

        return new AuthorizedSignatoryResource($authorizedSignatory);
    }

    public function update(AuthorizedSignatoryRequest $request, AuthorizedSignatory $authorizedSignatory)
    {
        $this->authorize('update', $authorizedSignatory);

        $authorizedSignatory->update($request->validated());

        return new AuthorizedSignatoryResource($authorizedSignatory);
    }

    public function destroy(AuthorizedSignatory $authorizedSignatory)
    {
        $this->authorize('delete', $authorizedSignatory);

        $authorizedSignatory->delete();

        return response()->json();
    }
}
