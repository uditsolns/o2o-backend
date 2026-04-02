<?php

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
        Schema::create('customer_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('trip_type', TripType::values());
            $table->enum('transport_mode', TripTransportationMode::values());
            $table->foreignId('dispatch_location_id')->nullable()->constrained('customer_locations')->nullOnDelete();
            $table->foreignId('delivery_location_id')->nullable()->constrained('customer_locations')->nullOnDelete();
            $table->foreignId('origin_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('destination_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_routes');
    }
};
