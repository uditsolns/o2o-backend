<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('trip_shipment_milestones', function (Blueprint $table) {
            $table->string('vessel_operational_status', 50)
                ->nullable()
                ->after('voyage_number');
        });
    }

    public function down(): void
    {
        Schema::table('trip_shipment_milestones', function (Blueprint $table) {
            $table->dropColumn('vessel_operational_status');
        });
    }
};
