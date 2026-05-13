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
        return [
            'tracking_status' => $this->tracking_status,
            'failed_reason' => $this->failed_reason,
            'transportation_status' => $this->transportation_status,
            'arrival_delay_days' => $this->arrival_delay_days,
            'initial_carrier_eta' => $this->initial_carrier_eta,
            'has_rollover' => $this->has_rollover,
            'eta_history' => $this->eta_history ?? [],
            'rollover_history' => $this->rollover_history ?? [],
            'transshipment_ports' => $this->transshipment_ports ?? [],
            'pol' => ['name' => $this->pol_name, 'unlocode' => $this->pol_unlocode],
            'pod' => ['name' => $this->pod_name, 'unlocode' => $this->pod_unlocode],
            'current_vessel' => [
                'name' => $this->current_vessel_name,
                'imo' => $this->current_vessel_imo,
                'lat' => $this->current_vessel_lat,
                'lng' => $this->current_vessel_lng,
                'speed_knots' => $this->current_vessel_speed,
                'heading' => $this->current_vessel_heading,
                'geo_area' => $this->current_vessel_geo_area,
                'position_at' => $this->current_vessel_position_at,
            ],
            'last_synced_at' => $this->last_synced_at,
        ];
    }
}
