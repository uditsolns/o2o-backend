<?php

use App\Enums\TripSegmentTrackingSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_tracking_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('source', TripSegmentTrackingSource::values());
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('speed', 8, 2)->nullable()->comment('km/h');
            $table->smallInteger('heading')->nullable()->comment('0-359 degrees');
            $table->integer('accuracy')->nullable()->comment('meters');
            $table->string('location_name')->nullable()->comment('toll plaza name, landmark, etc.');
            $table->string('external_id', 150)->nullable()->comment('seqNo for fastag — deduplication key');
            $table->timestamp('recorded_at')->comment('When the event actually occurred');
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trip_id', 'recorded_at']);
            $table->index(['trip_id', 'source', 'recorded_at']);
            // Deduplication: same trip + source + external_id is one event
            $table->unique(['trip_id', 'source', 'external_id'], 'uq_tracking_dedup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_tracking_points');
    }
};
