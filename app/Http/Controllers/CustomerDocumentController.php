<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerDocumentRequest;
use App\Http\Resources\CustomerDocumentResource;
use App\Models\CustomerDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomerDocumentController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CustomerDocument::class);

        return CustomerDocumentResource::collection(CustomerDocument::all());
    }

    public function store(CustomerDocumentRequest $request)
    {
        $this->authorize('create', CustomerDocument::class);

        return new CustomerDocumentResource(CustomerDocument::create($request->validated()));
    }

    public function show(CustomerDocument $customerDocument)
    {
        $this->authorize('view', $customerDocument);

        return new CustomerDocumentResource($customerDocument);
    }

    public function update(CustomerDocumentRequest $request, CustomerDocument $customerDocument)
    {
        $this->authorize('update', $customerDocument);

        $customerDocument->update($request->validated());

        return new CustomerDocumentResource($customerDocument);
    }

    public function destroy(CustomerDocument $customerDocument)
    {
        $this->authorize('delete', $customerDocument);

        $customerDocument->delete();

        return response()->json();
    }
}
