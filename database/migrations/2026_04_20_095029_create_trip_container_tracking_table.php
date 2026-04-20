<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_container_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('container_number', 20);
            $table->string('carrier_scac', 10);
            $table->string('mt_tracking_request_id')->nullable()->unique();
            $table->string('mt_shipment_id')->nullable()->unique();
            $table->enum('tracking_status', ['not_registered', 'pending', 'active', 'failed'])
                ->default('not_registered');
            $table->string('failed_reason')->nullable();
            // Kpler transportation status: booked, in_transit, delivered, etc.
            $table->string('transportation_status')->nullable();
            $table->smallInteger('arrival_delay_days')->nullable();
            $table->timestamp('initial_carrier_eta')->nullable();
            $table->boolean('has_rollover')->default(false);
            $table->string('pol_name')->nullable()->comment('Port of Loading name');
            $table->string('pol_unlocode', 10)->nullable();
            $table->string('pod_name')->nullable()->comment('Port of Discharge name');
            $table->string('pod_unlocode', 10)->nullable();
            // Current vessel snapshot (from container API)
            $table->string('current_vessel_name')->nullable();
            $table->string('current_vessel_imo')->nullable();
            $table->decimal('current_vessel_lat', 10, 7)->nullable();
            $table->decimal('current_vessel_lng', 10, 7)->nullable();
            $table->decimal('current_vessel_speed', 6, 2)->nullable();
            $table->smallInteger('current_vessel_heading')->nullable();
            $table->string('current_vessel_geo_area')->nullable();
            $table->timestamp('current_vessel_position_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_shipment_snapshot')->nullable();
            $table->timestamps();

            $table->index(['tracking_status']);
            $table->index(['mt_shipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_container_tracking');
    }
};
