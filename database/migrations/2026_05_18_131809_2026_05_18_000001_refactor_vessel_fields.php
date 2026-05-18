<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'vessel_name',
                'vessel_imo_number',
                'voyage_number',
                'mt_vessel_ship_id',
                'last_vessel_tracked_at',
                'last_vessel_position_at',
            ]);
        });

        Schema::table('trip_container_tracking', function (Blueprint $table) {
            // Moved from trips
            $table->string('mt_vessel_ship_id')->nullable()->after('carrier_scac');
            $table->timestamp('last_vessel_position_at')->nullable()->after('last_synced_at');

            // POL enrichment
            $table->decimal('pol_lat', 10, 7)->nullable()->after('pol_unlocode');
            $table->decimal('pol_lng', 10, 7)->nullable()->after('pol_lat');
            $table->string('pol_country', 100)->nullable()->after('pol_lng');
            $table->timestamp('pol_etd')->nullable()->after('pol_country');
            $table->string('pol_vessel_name')->nullable()->after('pol_etd');
            $table->string('pol_vessel_imo', 20)->nullable()->after('pol_vessel_name');
            $table->string('pol_voyage_number', 50)->nullable()->after('pol_vessel_imo');

            // POD enrichment
            $table->decimal('pod_lat', 10, 7)->nullable()->after('pod_unlocode');
            $table->decimal('pod_lng', 10, 7)->nullable()->after('pod_lat');
            $table->string('pod_country', 100)->nullable()->after('pod_lng');
            $table->string('pod_arrival_status', 10)->nullable()->after('pod_country');
            $table->timestamp('pod_actual_arrival')->nullable()->after('pod_arrival_status');

            // Carrier full name
            $table->string('carrier_name', 200)->nullable()->after('carrier_scac');

            // Container specs
            $table->string('container_iso_code', 10)->nullable()->after('container_number');
            $table->string('container_type_name', 100)->nullable()->after('container_iso_code');
            $table->json('container_size')->nullable()->after('container_type_name');

            // Current vessel AIS enrichment
            $table->string('current_vessel_mmsi', 20)->nullable()->after('current_vessel_imo');
            $table->string('current_vessel_destination', 200)->nullable()->after('current_vessel_geo_area');
            $table->string('current_vessel_current_port', 200)->nullable()->after('current_vessel_destination');
            $table->timestamp('current_vessel_ais_eta')->nullable()->after('current_vessel_current_port');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('vessel_name')->nullable();
            $table->string('vessel_imo_number', 20)->nullable();
            $table->string('voyage_number', 100)->nullable();
            $table->string('mt_vessel_ship_id')->nullable();
            $table->timestamp('last_vessel_tracked_at')->nullable();
            $table->timestamp('last_vessel_position_at')->nullable();
        });

        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->dropColumn([
                'mt_vessel_ship_id', 'last_vessel_position_at',
                'pol_lat', 'pol_lng', 'pol_country', 'pol_etd',
                'pol_vessel_name', 'pol_vessel_imo', 'pol_voyage_number',
                'pod_lat', 'pod_lng', 'pod_country', 'pod_arrival_status', 'pod_actual_arrival',
                'carrier_name', 'container_iso_code', 'container_type_name', 'container_size',
                'current_vessel_mmsi', 'current_vessel_destination',
                'current_vessel_current_port', 'current_vessel_ais_eta',
            ]);
        });
    }
};
