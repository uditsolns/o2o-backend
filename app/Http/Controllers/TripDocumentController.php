<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trip\UploadTripDocumentRequest;
use App\Http\Resources\TripDocumentResource;
use App\Models\Trip;
use App\Models\TripDocument;
use App\Services\TripDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripDocumentController extends Controller
{
    public function __construct(private readonly TripDocumentService $service)
    {
    }

    public function index(Trip $trip): AnonymousResourceCollection
    {
        $this->authorize('view', $trip);

        $documents = $trip->documents()
            ->with('uploadedBy')
            ->latest('created_at')
            ->get();

        return TripDocumentResource::collection($documents);
    }

    public function store(UploadTripDocumentRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('upload', TripDocument::class);
        $this->authorize('view', $trip); // ensure trip belongs to tenant

        abort_if($trip->isLocked(), 403, 'Cannot upload documents to a completed trip.');

        $document = $this->service->store(
            $trip,
            $request->file('file'),
            $request->validated('doc_type'),
            $request->user()
        );

        return response()->json(new TripDocumentResource(
            $document->load('uploadedBy')
        ), 201);
    }

    public function destroy(Trip $trip, TripDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        abort_if($document->trip_id !== $trip->id, 404);
        abort_if($trip->isLocked(), 403, 'Cannot delete documents from a completed trip.');

        $this->service->delete($document);

        return response()->json(['message' => 'Document deleted.']);
    }
}
