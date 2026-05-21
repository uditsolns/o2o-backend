<?php

namespace App\Http\Resources;

use App\Models\TripContainerTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripContainerTracking */
class TripContainerTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $insights = $this->insights ?? [];

        return [
            'tracking_status' => $this->tracking_status,
            'failed_reason' => $this->failed_reason,
            'transportation_status' => $this->transportation_status,
            'transportation_status_updated_at' => $this->transportation_status_updated_at,
            'is_routing_inconclusive' => $this->is_routing_inconclusive,

            // Insights — flattened for convenience
            'arrival_delay_days' => data_get($insights, 'arrival_delay_days'),
            'initial_carrier_eta' => data_get($insights, 'initial_carrier_eta'),
            'has_rollover' => data_get($insights, 'has_rollover', false),

            // History arrays
            'eta_history' => $this->eta_history ?? [],
            'rollover_history' => $this->rollover_history ?? [],
            'transshipment_ports' => $this->normalizeTransshipmentPorts(),
            'pol_change_history' => $this->pol_change_history ?? [],
            'pod_change_history' => $this->pod_change_history ?? [],

            // Nested snapshots — served as objects from JSON columns
            'carrier' => $this->carrier ?? ['scac' => null, 'name' => null],
            'container' => $this->container_specs ?? ['iso_code' => null, 'type' => null, 'size' => null],
            'pol' => $this->pol ?? [],
            'pod' => $this->pod ?? [],
            'current_vessel' => $this->current_vessel ?? [],

            'last_vessel_position_at' => $this->last_vessel_position_at,
            'last_synced_at' => $this->last_synced_at,
        ];
    }

    /**
     * Ensure transshipment port coordinates use `lng` not `lon`.
     * Belt-and-suspenders normalization on top of the ingest normalization.
     */
    private function normalizeTransshipmentPorts(): array
    {
        $ports = $this->transshipment_ports ?? [];

        return array_map(function (array $entry) {
            if (isset($entry['port']['lon']) && !isset($entry['port']['lng'])) {
                $entry['port']['lng'] = $entry['port']['lon'];
                unset($entry['port']['lon']);
            }
            return $entry;
        }, $ports);
    }
}
