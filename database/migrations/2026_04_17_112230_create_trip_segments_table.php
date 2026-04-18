<?php

use App\Enums\{TripSegmentTrackingSource, TripSegmentTransportMode};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('source_name');
            $table->string('destination_name');
            $table->enum('transport_mode', TripSegmentTransportMode::values());
            $table->enum('tracking_source', TripSegmentTrackingSource::values())->nullable()
                ->comment('Only applicable for road transport');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_segments');
    }
};
