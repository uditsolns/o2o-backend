<?php

use App\Enums\{PortCategory, TripTransportationMode, TripType};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->enum('trip_type', TripType::values());
            $table->enum('transport_mode', TripTransportationMode::values());
            // Dispatch snapshot
            $table->string('dispatch_location_name')->nullable();
            $table->text('dispatch_address')->nullable();
            $table->string('dispatch_city', 100)->nullable();
            $table->string('dispatch_state', 100)->nullable();
            $table->string('dispatch_pincode', 10)->nullable();
            $table->string('dispatch_country', 100)->default('India');
            $table->decimal('dispatch_lat', 10, 7)->nullable();
            $table->decimal('dispatch_lng', 10, 7)->nullable();
            // Delivery snapshot
            $table->string('delivery_location_name')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_state', 100)->nullable();
            $table->string('delivery_pincode', 10)->nullable();
            $table->string('delivery_country', 100)->default('India');
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            // Port snapshots
            $table->string('origin_port_name')->nullable();
            $table->string('origin_port_code', 20)->nullable();
            $table->enum('origin_port_category', PortCategory::values())->nullable();
            $table->string('destination_port_name')->nullable();
            $table->string('destination_port_code', 20)->nullable();
            $table->enum('destination_port_category', PortCategory::values())->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->index(['customer_id', 'trip_type', 'transport_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_routes');
    }
};
