<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            // New JSON snapshot columns
            $table->json('carrier')->nullable()->after('carrier_scac')
                ->comment('{scac, name}');
            $table->json('container_specs')->nullable()->after('carrier')
                ->comment('{iso_code, type, size}');
            $table->json('pol')->nullable()->after('container_specs')
                ->comment('Full port of loading snapshot');
            $table->json('pod')->nullable()->after('pol')
                ->comment('Full port of discharge snapshot');
            $table->json('current_vessel')->nullable()->after('pod')
                ->comment('Full current vessel snapshot including operational_status');
            $table->json('insights')->nullable()->after('current_vessel')
                ->comment('{arrival_delay_days, initial_carrier_eta, has_rollover}');
            $table->json('pol_change_history')->nullable()->after('insights')
                ->comment('insights.portOfLoadingChange[] from Kpler');
            $table->json('pod_change_history')->nullable()->after('pol_change_history')
                ->comment('insights.portOfDischargeChange[] from Kpler');
        });

        // Backfill JSON columns from existing flat columns
        DB::statement("
            UPDATE trip_container_tracking SET
                carrier = JSON_OBJECT(
                    'scac', COALESCE(carrier_scac, ''),
                    'name', COALESCE(carrier_name, '')
                ),
                container_specs = JSON_OBJECT(
                    'iso_code', container_iso_code,
                    'type', container_type_name,
                    'size', COALESCE(container_size, 'null')
                ),
                pol = JSON_OBJECT(
                    'name', pol_name,
                    'unlocode', pol_unlocode,
                    'lat', pol_lat,
                    'lng', pol_lng,
                    'country', pol_country,
                    'etd', pol_etd,
                    'loading_vessel', JSON_OBJECT(
                        'name', pol_vessel_name,
                        'imo', pol_vessel_imo,
                        'voyage', pol_voyage_number
                    )
                ),
                pod = JSON_OBJECT(
                    'name', pod_name,
                    'unlocode', pod_unlocode,
                    'lat', pod_lat,
                    'lng', pod_lng,
                    'country', pod_country,
                    'arrival_status', pod_arrival_status,
                    'arrival_at', pod_actual_arrival
                ),
                current_vessel = JSON_OBJECT(
                    'name', current_vessel_name,
                    'imo', current_vessel_imo,
                    'mmsi', current_vessel_mmsi,
                    'lat', current_vessel_lat,
                    'lng', current_vessel_lng,
                    'speed_knots', current_vessel_speed,
                    'heading', current_vessel_heading,
                    'geo_area', current_vessel_geo_area,
                    'destination', current_vessel_destination,
                    'current_port', current_vessel_current_port,
                    'ais_eta', current_vessel_ais_eta,
                    'position_at', current_vessel_position_at,
                    'operational_status', NULL
                ),
                insights = JSON_OBJECT(
                    'arrival_delay_days', arrival_delay_days,
                    'initial_carrier_eta', initial_carrier_eta,
                    'has_rollover', has_rollover
                )
        ");

        // Drop the flat columns that are now inside JSON
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->dropColumn([
                'carrier_name',
                'container_iso_code',
                'container_type_name',
                'container_size',
                'pol_name',
                'pol_unlocode',
                'pol_lat',
                'pol_lng',
                'pol_country',
                'pol_etd',
                'pol_vessel_name',
                'pol_vessel_imo',
                'pol_voyage_number',
                'pod_name',
                'pod_unlocode',
                'pod_lat',
                'pod_lng',
                'pod_country',
                'pod_arrival_status',
                'pod_actual_arrival',
                'current_vessel_name',
                'current_vessel_mmsi',
                'current_vessel_lat',
                'current_vessel_lng',
                'current_vessel_speed',
                'current_vessel_heading',
                'current_vessel_geo_area',
                'current_vessel_destination',
                'current_vessel_current_port',
                'current_vessel_ais_eta',
                'current_vessel_position_at',
                'arrival_delay_days',
                'initial_carrier_eta',
                'has_rollover',
            ]);
        });

        // current_vessel_imo stays as flat operational column (queried by VesselAisPollJob)
        // Add index on transportation_status for job queries
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->index('transportation_status');
        });
    }

    public function down(): void
    {
        // Re-add flat columns
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->string('carrier_name', 200)->nullable();
            $table->string('container_iso_code', 10)->nullable();
            $table->string('container_type_name', 100)->nullable();
            $table->json('container_size')->nullable();
            $table->string('pol_name')->nullable();
            $table->string('pol_unlocode', 10)->nullable();
            $table->decimal('pol_lat', 10, 7)->nullable();
            $table->decimal('pol_lng', 10, 7)->nullable();
            $table->string('pol_country', 100)->nullable();
            $table->timestamp('pol_etd')->nullable();
            $table->string('pol_vessel_name')->nullable();
            $table->string('pol_vessel_imo', 20)->nullable();
            $table->string('pol_voyage_number', 50)->nullable();
            $table->string('pod_name')->nullable();
            $table->string('pod_unlocode', 10)->nullable();
            $table->decimal('pod_lat', 10, 7)->nullable();
            $table->decimal('pod_lng', 10, 7)->nullable();
            $table->string('pod_country', 100)->nullable();
            $table->string('pod_arrival_status', 10)->nullable();
            $table->timestamp('pod_actual_arrival')->nullable();
            $table->string('current_vessel_name')->nullable();
            $table->string('current_vessel_mmsi', 20)->nullable();
            $table->decimal('current_vessel_lat', 10, 7)->nullable();
            $table->decimal('current_vessel_lng', 10, 7)->nullable();
            $table->decimal('current_vessel_speed', 6, 2)->nullable();
            $table->smallInteger('current_vessel_heading')->nullable();
            $table->string('current_vessel_geo_area')->nullable();
            $table->string('current_vessel_destination', 200)->nullable();
            $table->string('current_vessel_current_port', 200)->nullable();
            $table->timestamp('current_vessel_ais_eta')->nullable();
            $table->timestamp('current_vessel_position_at')->nullable();
            $table->smallInteger('arrival_delay_days')->nullable();
            $table->timestamp('initial_carrier_eta')->nullable();
            $table->boolean('has_rollover')->default(false);
            $table->dropColumn([
                'carrier', 'container_specs', 'pol', 'pod',
                'current_vessel', 'insights',
                'pol_change_history', 'pod_change_history',
            ]);
        });
    }
};
