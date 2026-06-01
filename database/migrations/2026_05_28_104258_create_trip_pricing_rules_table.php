<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_pricing_rules', function (Blueprint $table) {
            $table->id();
            // null = global/default rule; non-null = customer-specific override
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('trip_type', ['import', 'export', 'domestic']);
            $table->enum('transport_mode', ['road', 'sea', 'multimodal']);
            $table->decimal('price_per_trip', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One rule per trip_type + transport_mode per customer (or global)
            $table->unique(['customer_id', 'trip_type', 'transport_mode'], 'uq_trip_pricing_rule');
            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_pricing_rules');
    }
};
