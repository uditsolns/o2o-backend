<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seal_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('min_quantity');
            $table->integer('max_quantity')->nullable();
            $table->integer('max_quantity_key')->virtualAs('COALESCE(`max_quantity`, 0)');
            $table->decimal('price_per_seal', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'min_quantity', 'max_quantity_key'], 'uq_pricing_tier_range');
            $table->index(['customer_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seal_pricing_tiers');
    }
};
