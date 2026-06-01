<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 2026_05_26_000006_alter_orders_wallet_customers_documents.php

    public function up(): void
    {
        // seal_orders: add parent_order_id and payment_status
        Schema::table('seal_orders', function (Blueprint $table) {
            $table->foreignId('parent_order_id')
                ->nullable()
                ->after('id')
                ->comment('Links to the rejected order this order was created to replace')
                ->constrained('seal_orders')
                ->nullOnDelete();

            $table->enum('payment_status', [
                'not_applicable',   // advance_balance and credit orders
                'pending_payment',  // cash orders awaiting offline payment confirmation
                'payment_received', // platform confirmed cash received
            ])->default('not_applicable')->after('payment_type');
        });

        // customer_wallets: remove insurance fields, add low_balance_threshold ─
        Schema::table('customer_wallets', function (Blueprint $table) {
            $table->dropColumn([
                'il_policy_number',
                'il_policy_expiry',
                'sum_insured',
                'gwp',
            ]);
            $table->decimal('low_balance_threshold', 15, 2)
                ->nullable()
                ->after('cost_balance')
                ->comment('Alert when cost_balance drops below this value');
        });

        // customers: add insurance fields
        Schema::table('customers', function (Blueprint $table) {
            $table->string('il_policy_number', 100)->nullable()->after('il_remarks');
            $table->date('il_policy_expiry')->nullable()->after('il_policy_number');
            $table->decimal('sum_insured', 15, 2)->nullable()->after('il_policy_expiry');
            $table->decimal('gwp', 15, 2)->nullable()->after('sum_insured');
        });

        // customer_documents: track Sepio rejection reason
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->string('sepio_rejection_reason')->nullable()->after('sepio_file_name');
        });

        // customer_wallet_transactions: expand reference_type, add trip_id
        // Alter enum via temp column pattern
        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->string('reference_type_new', 30)->nullable()->after('reference_type');
        });

        DB::statement("UPDATE customer_wallet_transactions SET reference_type_new = reference_type");

        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('reference_type');
        });

        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->enum('reference_type', [
                'advance_debit',
                'advance_refund',
                'credit_draw',
                'credit_release',
                'credit_settlement',
                'manual_topup',
                'trip_debit',
                'trip_refund',
                'cash_payment_received',
            ])->nullable()->after('reference_type_new');
        });

        // Map old values to new
        DB::statement("
        UPDATE customer_wallet_transactions SET reference_type = CASE reference_type_new
            WHEN 'order'   THEN 'advance_debit'
            WHEN 'refund'  THEN 'advance_refund'
            WHEN 'manual'  THEN 'manual_topup'
            ELSE 'manual_topup'
        END
    ");

        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('reference_type_new');
            // Add trip_id for trip_debit transactions
            $table->foreignId('trip_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('trips')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reversal omitted for brevity — restore from backup in production
    }
};
