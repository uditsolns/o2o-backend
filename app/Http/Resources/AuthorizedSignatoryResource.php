<?php

namespace App\Http\Resources;

use App\Models\AuthorizedSignatory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin AuthorizedSignatory */
class AuthorizedSignatoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'id_proof_url' => $this->id_proof_url
                ? Storage::temporaryUrl($this->id_proof_url, now()->addMinutes(30))
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
