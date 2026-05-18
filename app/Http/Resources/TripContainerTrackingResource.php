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

            'carrier' => [
                'scac' => $this->carrier_scac,
                'name' => $this->carrier_name,
            ],

            'container' => [
                'number' => $this->container_number,
                'iso_code' => $this->container_iso_code,
                'type' => $this->container_type_name,
                'size' => $this->container_size,
            ],

            'pol' => [
                'name' => $this->pol_name,
                'unlocode' => $this->pol_unlocode,
                'country' => $this->pol_country,
                'lat' => $this->pol_lat,
                'lng' => $this->pol_lng,
                'etd' => $this->pol_etd,
                'loading_vessel' => [
                    'name' => $this->pol_vessel_name,
                    'imo' => $this->pol_vessel_imo,
                    'voyage' => $this->pol_voyage_number,
                ],
            ],

            'pod' => [
                'name' => $this->pod_name,
                'unlocode' => $this->pod_unlocode,
                'country' => $this->pod_country,
                'lat' => $this->pod_lat,
                'lng' => $this->pod_lng,
                'arrival_status' => $this->pod_arrival_status,
                'arrival_at' => $this->pod_actual_arrival,
            ],

            'current_vessel' => [
                'name' => $this->current_vessel_name,
                'imo' => $this->current_vessel_imo,
                'mmsi' => $this->current_vessel_mmsi,
                'lat' => $this->current_vessel_lat,
                'lng' => $this->current_vessel_lng,
                'speed_knots' => $this->current_vessel_speed,
                'heading' => $this->current_vessel_heading,
                'geo_area' => $this->current_vessel_geo_area,
                'destination' => $this->current_vessel_destination,
                'current_port' => $this->current_vessel_current_port,
                'ais_eta' => $this->current_vessel_ais_eta,
                'position_at' => $this->current_vessel_position_at,
            ],

            'last_vessel_position_at' => $this->last_vessel_position_at,
            'last_synced_at' => $this->last_synced_at,
        ];
    }
}
