<?php

use App\Enums\WalletCoastingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('il_policy_number', 100)->nullable();
            $table->date('il_policy_expiry')->nullable();
            $table->decimal('sum_insured', 15, 2)->nullable();
            $table->decimal('gwp', 15, 2)->nullable();
            $table->enum('costing_type', WalletCoastingType::values())
                ->default(WalletCoastingType::Cash->value);
            $table->integer('credit_period')->nullable();
            $table->decimal('credit_capping', 15, 2)->nullable();
            $table->decimal('credit_used', 15, 2)->default(0);
            $table->decimal('freight_rate_per_seal', 10, 2)->default(0);
            $table->decimal('cost_balance', 15, 2)->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
