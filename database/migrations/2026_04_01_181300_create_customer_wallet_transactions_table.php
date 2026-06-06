<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);

            // reference_type is a free-form string — wallet entries can be tied
            // to many different domain objects (orders, refunds, credit draws,
            // cash payments, etc.), so the enum that lived here originally was
            // too restrictive.
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // trip_id captures wallet activity that originates from a specific
            // trip (e.g. trip cost deductions on ePOD). The FK is added in
            // 2026_04_01_182341_create_add_deferred_seal_trip_fks because trips
            // doesn't exist yet at this point in the migration order.
            $table->unsignedBigInteger('trip_id')->nullable();

            $table->decimal('balance_after', 15, 2);
            $table->string('balance_type', 20)->nullable()
                ->comment('Which balance balance_after refers to: cost_balance or available_credit');
            $table->string('receipt_file_url')->nullable()
                ->comment('Optional receipt for top-ups, credit settlements, cash confirmations');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
    }
};
