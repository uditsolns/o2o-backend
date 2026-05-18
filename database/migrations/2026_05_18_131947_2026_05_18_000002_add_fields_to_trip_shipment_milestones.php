<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_shipment_milestones', function (Blueprint $table) {
            $table->enum('event_category', ['transport', 'equipment'])->after('event_type');
            $table->string('mode_of_transport', 30)->nullable()->after('voyage_number');
            $table->string('vessel_mmsi', 20)->nullable()->after('vessel_imo');
            $table->decimal('local_time_offset', 4, 2)->nullable()->after('location_type');
            $table->string('equipment_indicator', 10)->nullable()->after('local_time_offset')
                ->comment('laden or empty');
        });
    }

    public function down(): void
    {
        Schema::table('trip_shipment_milestones', function (Blueprint $table) {
            $table->dropColumn([
                'event_category', 'mode_of_transport',
                'vessel_mmsi', 'local_time_offset', 'equipment_indicator',
            ]);
        });
    }
};
