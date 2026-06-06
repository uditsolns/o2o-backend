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

            // JSON-blob design (replaces the flat carrier/POL/POD/vessel
            // columns we used originally) — keeps the schema compact while
            // preserving every field Kpler ships in its shipment payload.
            $table->json('carrier')->nullable()
                ->comment('{scac, name}');
            $table->json('container_specs')->nullable()
                ->comment('{iso_code, type, size}');
            $table->json('pol')->nullable()
                ->comment('Full port of loading snapshot');
            $table->json('pod')->nullable()
                ->comment('Full port of discharge snapshot');
            $table->json('current_vessel')->nullable()
                ->comment('Full current vessel snapshot including operational_status');
            $table->json('insights')->nullable()
                ->comment('{arrival_delay_days, initial_carrier_eta, has_rollover}');
            $table->json('pol_change_history')->nullable()
                ->comment('insights.portOfLoadingChange[] from Kpler');
            $table->json('pod_change_history')->nullable()
                ->comment('insights.portOfDischargeChange[] from Kpler');

            // MarineTraffic registration bookkeeping
            $table->string('mt_vessel_ship_id')->nullable();
            $table->string('mt_tracking_request_id')->nullable();
            $table->string('mt_shipment_id')->nullable()->unique();

            $table->enum('tracking_status', ['not_registered', 'pending', 'active', 'failed'])
                ->default('not_registered');
            $table->string('failed_reason')->nullable();

            $table->string('transportation_status')->nullable();
            $table->boolean('is_routing_inconclusive')->default(false)
                ->comment('Set when Kpler returns routing_data_inconclusive');
            $table->timestamp('transportation_status_updated_at')->nullable()
                ->comment('When transportation_status last changed');

            $table->string('current_vessel_imo')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_vessel_position_at')->nullable();

            $table->json('raw_shipment_snapshot')->nullable();
            $table->json('eta_history')->nullable()
                ->comment('Array of {eta, recorded_at} pairs — full ETA change history');
            $table->json('rollover_history')->nullable()
                ->comment('Vessel rollover events received from Kpler');
            $table->json('transshipment_ports')->nullable()
                ->comment('Intermediate port stops from Kpler portsOfTransshipment');

            $table->timestamps();

            $table->index('tracking_status');
            $table->index('mt_shipment_id');
            $table->index('transportation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_container_tracking');
    }
};
