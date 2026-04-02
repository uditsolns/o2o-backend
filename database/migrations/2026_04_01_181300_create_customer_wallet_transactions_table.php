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
        Schema::create('customer_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->enum('reference_type', ['order', 'manual', 'refund'])->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 15, 2);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
    }
};
