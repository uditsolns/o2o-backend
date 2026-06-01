<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // ── Step 1: Add balance_type and receipt_file_url ─────────────────────
        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->string('balance_type', 20)
                ->nullable()
                ->after('balance_after')
                ->comment('Which balance balance_after refers to: cost_balance or available_credit');

            $table->string('receipt_file_url')
                ->nullable()
                ->after('balance_type')
                ->comment('Optional receipt for top-ups, credit settlements, cash confirmations');
        });

        // ── Step 2: Backfill balance_type for existing rows ───────────────────
        // All transactions before this migration were cost_balance operations
        DB::statement("
            UPDATE customer_wallet_transactions
            SET balance_type = 'cost_balance'
            WHERE balance_type IS NULL
        ");

        // ── Step 3: Add trip_id FK (only if not already added) ────────────────
        if (!Schema::hasColumn('customer_wallet_transactions', 'trip_id')) {
            Schema::table('customer_wallet_transactions', function (Blueprint $table) {
                $table->foreignId('trip_id')
                    ->nullable()
                    ->after('reference_id')
                    ->constrained('trips')
                    ->nullOnDelete();
            });
        }

        // ── Step 4: Change reference_type from enum to string if needed ───────
        $col = DB::selectOne("
            SELECT DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'customer_wallet_transactions'
              AND COLUMN_NAME  = 'reference_type'
        ");

        if ($col && strtolower($col->DATA_TYPE) === 'enum') {
            DB::statement("
                ALTER TABLE customer_wallet_transactions
                MODIFY COLUMN reference_type VARCHAR(30) NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_type', 'receipt_file_url']);
        });

        if (Schema::hasColumn('customer_wallet_transactions', 'trip_id')) {
            Schema::table('customer_wallet_transactions', function (Blueprint $table) {
                $table->dropForeign(['trip_id']);
                $table->dropColumn('trip_id');
            });
        }
    }
};
