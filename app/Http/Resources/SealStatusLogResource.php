<?php

namespace App\Http\Resources;

use App\Models\SealStatusLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SealStatusLog */
class SealStatusLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'scan_location' => $this->scan_location,
            'scanned_lat' => $this->scanned_lat,
            'scanned_lng' => $this->scanned_lng,
            'scanned_by' => $this->scanned_by,
            'checked_at' => $this->checked_at,
        ];
    }
}
