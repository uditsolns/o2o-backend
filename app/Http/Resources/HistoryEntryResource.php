<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Shared transformer for the three lifecycle history tables — onboarding,
 * sepio, seal-order. All three share the same column shape; the resource
 * surfaces:
 *
 *   - `actor.type`  → 'user' or 'system' (matches stored column)
 *   - `actor.kind`  → 'platform' or 'customer' (derived from users.customer_id;
 *                     null for system events)
 *   - `actor.id` / `actor.name` → resolved when the user is present
 *
 * The derived `kind` field replaces the old platform/customer enum split that
 * used to be stored on the row. Storing it would duplicate data already
 * available via the users join — which would even go stale if the user later
 * changed role — so we compute it on read instead.
 */
class HistoryEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'actor' => $this->buildActor(),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];

        // Optional columns — included only when the underlying table has them.
        if (array_key_exists('remarks_file_url', $this->resource->getAttributes())) {
            $data['remarks_file_url'] = $this->resource->remarks_file_url
                ? Storage::temporaryUrl($this->resource->remarks_file_url, now()->addMinutes(30))
                : null;
        }

        if (array_key_exists('rejected_documents', $this->resource->getAttributes())) {
            $data['rejected_documents'] = $this->resource->rejected_documents;
        }

        return $data;
    }

    private function buildActor(): array
    {
        if ($this->actor_type === 'system' || !$this->actor_id) {
            return [
                'type' => 'system',
                'kind' => null,
                'id' => null,
                'name' => null,
            ];
        }

        $user = $this->whenLoaded('actor', $this->actor);
        // Fallback if relation wasn't eager-loaded
        if (!$user || !is_object($user)) {
            $user = \App\Models\User::withoutGlobalScopes()->find($this->actor_id);
        }

        return [
            'type' => 'user',
            'kind' => $user && $user->customer_id ? 'customer' : 'platform',
            'id' => $this->actor_id,
            'name' => $user?->name,
        ];
    }
}
