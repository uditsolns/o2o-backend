<?php

use App\Enums\TripStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 100);
            $table->enum('previous_status', TripStatus::values())->nullable();
            $table->enum('new_status', TripStatus::values())->nullable();
            $table->json('event_data')->nullable();
            $table->enum('actor_type', ['user', 'system'])->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_events');
    }
};
