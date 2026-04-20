<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_shipment_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('mt_event_id')->nullable();
            // Equipment events: load, unload, customs_released, customs_hold, inspected, received
            // Transport events: departure, arrival
            $table->string('event_type', 50);
            $table->enum('event_classifier', ['actual', 'planned'])->default('planned');
            $table->string('location_name')->nullable();
            $table->string('location_unlocode', 10)->nullable();
            $table->string('location_country', 100)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('terminal_name')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('vessel_imo')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('location_type')->nullable()
                ->comment('port_of_loading, transshipment, port_of_discharge');
            $table->smallInteger('sequence_order')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trip_id', 'mt_event_id'], 'uq_milestone_event');
            $table->index(['trip_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_shipment_milestones');
    }
};
