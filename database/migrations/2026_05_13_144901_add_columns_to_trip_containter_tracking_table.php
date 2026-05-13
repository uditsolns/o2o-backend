<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->json('eta_history')
                ->nullable()
                ->after('raw_shipment_snapshot')
                ->comment('Array of {eta, recorded_at} pairs — full ETA change history');

            $table->json('rollover_history')
                ->nullable()
                ->after('eta_history')
                ->comment('Vessel rollover events received from Kpler');

            $table->json('transshipment_ports')
                ->nullable()
                ->after('rollover_history')
                ->comment('Intermediate port stops from Kpler portsOfTransshipment');
        });
    }

    public function down(): void
    {
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->dropColumn(['eta_history', 'rollover_history', 'transshipment_ports']);
        });
    }
};
