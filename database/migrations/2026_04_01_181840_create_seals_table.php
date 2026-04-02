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
        Schema::create('seals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('seal_order_id')->constrained('seal_orders')->restrictOnDelete();
            $table->unsignedBigInteger('trip_id')->nullable(); // deferred FK
            $table->string('seal_number', 100)->unique();
            $table->enum('status', SealStatus::values())
                ->default(SealStatus::InInventory->value);
            $table->enum('sepio_status', SepioSealStatus::values())
                ->default(SepioSealStatus::Unknown->value);
            $table->timestamp('last_scan_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sepio_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seals');
    }
};
