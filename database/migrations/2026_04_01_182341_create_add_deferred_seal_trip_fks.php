<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds FKs that can't be expressed at create time:
 *
 *   - seals.trip_id <-> trips.seal_id: circular reference between the two
 *     tables; resolved by creating both tables first and wiring up FKs here.
 *   - customer_wallet_transactions.trip_id -> trips: customer_wallet_transactions
 *     is created before trips in the migration order, so its FK is deferred too.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('seals', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->foreign('seal_id')->references('id')->on('seals')->restrictOnDelete();
        });
        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['seal_id']);
        });
        Schema::table('seals', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
    }
};
