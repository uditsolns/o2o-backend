<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse all lifecycle-history actor enums to ('user', 'system').
 *
 * Previously the three history tables each had a slightly different set of
 * values (platform/customer/system + occasionally 'job'). Splitting platform
 * vs customer at the column level duplicated information that's already
 * available via actor_id → users.role_id, and would even go stale if a user
 * later changed role. Splitting job vs system was a distinction without a
 * difference — neither has a human actor.
 *
 * After this migration:
 *   - user   → there is a human in actor_id
 *   - system → no human; the platform did this automatically (cron, queue
 *              job, webhook, event listener — all the same to the timeline)
 *
 * Also renames customer_sepio_history.{triggered_by_type,triggered_by_id}
 * → {actor_type,actor_id} so all three history tables share the same column
 * names and can be served by one resource transformer.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── customer_onboarding_history ──────────────────────────────────────
        // Widen first so the CASE update can write the new 'user' value, then
        // collapse the data, then narrow to the final two-value enum.
        DB::statement("
            ALTER TABLE customer_onboarding_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE customer_onboarding_history
            SET actor_type = CASE actor_type
                WHEN 'platform' THEN 'user'
                WHEN 'customer' THEN 'user'
                ELSE 'system'
            END
        ");

        DB::statement("
            ALTER TABLE customer_onboarding_history
            MODIFY COLUMN actor_type ENUM('user', 'system') NOT NULL
        ");

        // ── customer_sepio_history ───────────────────────────────────────────
        // Rename columns first so the table matches the other two; then widen,
        // collapse, narrow.
        Schema::table('customer_sepio_history', function ($table) {
            $table->renameColumn('triggered_by_type', 'actor_type');
        });
        Schema::table('customer_sepio_history', function ($table) {
            $table->renameColumn('triggered_by_id', 'actor_id');
        });

        DB::statement("
            ALTER TABLE customer_sepio_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'job', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE customer_sepio_history
            SET actor_type = CASE actor_type
                WHEN 'platform' THEN 'user'
                WHEN 'customer' THEN 'user'
                ELSE 'system'
            END
        ");

        DB::statement("
            ALTER TABLE customer_sepio_history
            MODIFY COLUMN actor_type ENUM('user', 'system') NOT NULL
        ");

        // ── seal_order_history ───────────────────────────────────────────────
        DB::statement("
            ALTER TABLE seal_order_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE seal_order_history
            SET actor_type = CASE actor_type
                WHEN 'platform' THEN 'user'
                WHEN 'customer' THEN 'user'
                ELSE 'system'
            END
        ");

        DB::statement("
            ALTER TABLE seal_order_history
            MODIFY COLUMN actor_type ENUM('user', 'system') NOT NULL
        ");
    }

    public function down(): void
    {
        // Reverse mapping is lossy — user rows can't perfectly recover the
        // platform/customer split, but we can guess from role:
        //   - actor_id IS NULL → system  (already true; no change needed)
        //   - actor_id matches a customer-scoped user → customer
        //   - actor_id matches a platform user → platform
        //
        // We do best-effort backfill then restore the original enums. Any
        // historic rows that can't be classified default to 'platform'.

        // ── customer_onboarding_history ──────────────────────────────────────
        DB::statement("
            ALTER TABLE customer_onboarding_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE customer_onboarding_history h
            LEFT JOIN users u ON u.id = h.actor_id
            SET h.actor_type = CASE
                WHEN h.actor_type = 'system' THEN 'system'
                WHEN u.customer_id IS NOT NULL THEN 'customer'
                ELSE 'platform'
            END
        ");

        DB::statement("
            ALTER TABLE customer_onboarding_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system') NOT NULL
        ");

        // ── customer_sepio_history ───────────────────────────────────────────
        DB::statement("
            ALTER TABLE customer_sepio_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'job', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE customer_sepio_history h
            LEFT JOIN users u ON u.id = h.actor_id
            SET h.actor_type = CASE
                WHEN h.actor_type = 'system' THEN 'system'
                WHEN u.customer_id IS NOT NULL THEN 'customer'
                ELSE 'platform'
            END
        ");

        DB::statement("
            ALTER TABLE customer_sepio_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'job') NOT NULL
        ");

        Schema::table('customer_sepio_history', function ($table) {
            $table->renameColumn('actor_id', 'triggered_by_id');
        });
        Schema::table('customer_sepio_history', function ($table) {
            $table->renameColumn('actor_type', 'triggered_by_type');
        });

        // ── seal_order_history ───────────────────────────────────────────────
        DB::statement("
            ALTER TABLE seal_order_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system', 'user') NOT NULL
        ");

        DB::statement("
            UPDATE seal_order_history h
            LEFT JOIN users u ON u.id = h.actor_id
            SET h.actor_type = CASE
                WHEN h.actor_type = 'system' THEN 'system'
                WHEN u.customer_id IS NOT NULL THEN 'customer'
                ELSE 'platform'
            END
        ");

        DB::statement("
            ALTER TABLE seal_order_history
            MODIFY COLUMN actor_type ENUM('platform', 'customer', 'system') NOT NULL
        ");
    }
};
