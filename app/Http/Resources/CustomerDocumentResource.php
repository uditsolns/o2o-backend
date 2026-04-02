<?php

namespace App\Http\Resources;

use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin CustomerDocument */
class CustomerDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->doc_type,
            'doc_number' => $this->doc_number,
            'file_name' => $this->file_name,
            'url' => Storage::temporaryUrl($this->url, now()->addMinutes(30)),
            'sepio_file_name' => $this->sepio_file_name,
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn() => [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
