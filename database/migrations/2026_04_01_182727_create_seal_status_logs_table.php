<?php

use App\Enums\SealStatus;
use App\Enums\SepioSealStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seal_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('seal_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', SepioSealStatus::values());
            $table->string('scan_location')->nullable();
            $table->decimal('scanned_lat', 10, 7)->nullable();
            $table->decimal('scanned_lng', 10, 7)->nullable();
            $table->string('scanned_by')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seal_status_logs');
    }
};
