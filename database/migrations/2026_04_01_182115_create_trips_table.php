<?php

use App\Enums\PortCategory;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('seal_id')->nullable(); // deferred FK
            $table->foreignId('route_id')->nullable()->constrained('customer_routes')->nullOnDelete();
            $table->string('trip_ref', 30)->unique();
            $table->enum('status', TripStatus::values())->default(TripStatus::Draft->value);
            $table->enum('trip_type', TripType::values())->nullable();
            $table->enum('transport_mode', TripTransportationMode::values())->nullable();
            $table->decimal('risk_score', 5, 2)->nullable();
            // Driver
            $table->string('driver_name')->nullable();
            $table->string('driver_license', 50)->nullable();
            $table->string('driver_aadhaar', 20)->nullable();
            $table->string('driver_phone', 20)->nullable();
            // Vehicle
            $table->string('vehicle_number', 50)->nullable();
            $table->enum('vehicle_type', ['truck', 'trailer', 'container_carrier'])->nullable();
            $table->string('transporter_name')->nullable();
            $table->string('transporter_id', 100)->nullable();
            // Container
            $table->string('container_number', 50)->nullable();
            $table->string('container_type', 20)->nullable();
            $table->date('seal_issue_date')->nullable();
            // Cargo
            $table->string('cargo_type', 100)->nullable();
            $table->text('cargo_description')->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->decimal('gross_weight', 10, 3)->nullable();
            $table->decimal('net_weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->integer('quantity')->nullable();
            $table->string('quantity_unit', 50)->nullable();
            $table->decimal('declared_cargo_value', 15, 2)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('eway_bill_number', 50)->nullable();
            $table->date('eway_bill_validity_date')->nullable();
            // Dispatch snapshot
            $table->string('dispatch_location_name')->nullable();
            $table->text('dispatch_address')->nullable();
            $table->string('dispatch_city', 100)->nullable();
            $table->string('dispatch_state', 100)->nullable();
            $table->string('dispatch_pincode', 10)->nullable();
            $table->string('dispatch_country', 100)->nullable();
            $table->string('dispatch_contact_person')->nullable();
            $table->string('dispatch_contact_number', 20)->nullable();
            $table->decimal('dispatch_lat', 10, 7)->nullable();
            $table->decimal('dispatch_lng', 10, 7)->nullable();
            // Delivery snapshot
            $table->string('delivery_location_name')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_state', 100)->nullable();
            $table->string('delivery_pincode', 10)->nullable();
            $table->string('delivery_country', 100)->nullable();
            $table->string('delivery_contact_person')->nullable();
            $table->string('delivery_contact_number', 20)->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            // Port snapshots
            $table->string('origin_port_name')->nullable();
            $table->string('origin_port_code', 20)->nullable();
            $table->enum('origin_port_category', PortCategory::values())->nullable();
            $table->string('destination_port_name')->nullable();
            $table->string('destination_port_code', 20)->nullable();
            $table->enum('destination_port_category', PortCategory::values())->nullable();
            // Vessel
            $table->string('vessel_name')->nullable();
            $table->string('vessel_imo_number', 20)->nullable();
            $table->string('voyage_number', 100)->nullable();
            $table->string('bill_of_lading', 100)->nullable();
            $table->string('vessel_tracking_ref')->nullable();
            $table->json('vessel_tracking_data')->nullable();
            $table->timestamp('eta')->nullable();
            $table->timestamp('etd')->nullable();
            $table->timestamp('last_vessel_tracked_at')->nullable();
            // Timeline
            $table->date('dispatch_date')->nullable();
            $table->timestamp('trip_start_time')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->timestamp('trip_end_time')->nullable();
            // ePOD
            $table->enum('epod_status', ['pending', 'completed'])->default('pending');
            $table->timestamp('epod_confirmed_at')->nullable();
            $table->foreignId('epod_confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            // Destination confirmation
            $table->foreignId('destination_confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('destination_confirmation_notes')->nullable();
            $table->timestamp('destination_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('container_number');
            $table->index('dispatch_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
