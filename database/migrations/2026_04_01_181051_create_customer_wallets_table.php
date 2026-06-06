<?php

use App\Enums\WalletCoastingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();

            // Insurance-policy fields used to live here; they were moved to the
            // customers table because policy info is per-customer, not per-wallet.

            $table->enum('costing_type', WalletCoastingType::values())
                ->default(WalletCoastingType::Cash->value);
            $table->integer('credit_period')->nullable();
            $table->decimal('credit_capping', 15, 2)->nullable();
            $table->decimal('credit_used', 15, 2)->default(0);
            $table->decimal('freight_rate_per_seal', 10, 2)->default(0);
            $table->decimal('cost_balance', 15, 2)->default(0);
            $table->decimal('low_balance_threshold', 15, 2)->nullable()
                ->comment('Alert when cost_balance drops below this value');

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
