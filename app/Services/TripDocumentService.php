<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TripDocumentService
{
    public function store(Trip $trip, UploadedFile $file, string $docType, User $uploadedBy): TripDocument
    {
        $path = $file->store("trips/{$trip->id}/documents");

        return TripDocument::create([
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'uploaded_by_id' => $uploadedBy->id,
            'doc_type' => $docType,
            'file_name' => $file->getClientOriginalName(),
            'url' => $path,
        ]);
    }

    public function delete(TripDocument $document): void
    {
        Storage::delete($document->url);
        $document->delete();
    }
}
