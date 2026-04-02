<?php

namespace App\Http\Resources;

use App\Models\TripDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin TripDocument */
class TripDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->doc_type,
            'file_name' => $this->file_name,
            'url' => Storage::temporaryUrl($this->url, now()->addMinutes(30)),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn() => [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
