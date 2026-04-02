<?php

use App\Enums\LocationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('location_type', LocationType::values());
            $table->string('name');
            $table->string('gst_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('sepio_address_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'location_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_locations');
    }
};
