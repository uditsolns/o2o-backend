<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── TRIPS TABLE ───────────────────────────────────────────────────────

        // Step 1: Add temp column
        Schema::table('trips', function (Blueprint $table) {
            $table->string('status_new', 30)->nullable()->after('status');
        });

        // Step 2: Migrate data
        DB::statement("
            UPDATE trips SET status_new = CASE status
                WHEN 'draft'            THEN 'draft'
                WHEN 'in_transit'       THEN 'active'
                WHEN 'at_port'          THEN 'active'
                WHEN 'on_vessel'        THEN 'active'
                WHEN 'in_transshipment' THEN 'active'
                WHEN 'vessel_arrived'   THEN 'active'
                WHEN 'out_for_delivery' THEN 'out_for_delivery'
                WHEN 'delivered'        THEN 'delivered'
                WHEN 'completed'        THEN 'completed'
                ELSE 'active'
            END
        ");

        // Step 3: Drop the composite index BEFORE dropping the column
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropColumn('status');
        });

        // Step 4: Add new enum column
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'out_for_delivery', 'delivered', 'completed'])
                ->default('draft')
                ->after('status_new');
        });

        // Step 5: Copy from temp
        DB::statement("UPDATE trips SET status = status_new WHERE status_new IS NOT NULL");

        // Step 6: Drop temp column and re-add the index
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('status_new');
            $table->index(['customer_id', 'status']);
        });

        // ── TRIP_EVENTS TABLE ─────────────────────────────────────────────────

        // Step 1: Add temp columns
        Schema::table('trip_events', function (Blueprint $table) {
            $table->string('previous_status_new', 30)->nullable()->after('previous_status');
            $table->string('new_status_new', 30)->nullable()->after('new_status');
        });

        // Step 2: Migrate data
        DB::statement("
            UPDATE trip_events SET
                previous_status_new = CASE previous_status
                    WHEN 'draft'            THEN 'draft'
                    WHEN 'in_transit'       THEN 'active'
                    WHEN 'at_port'          THEN 'active'
                    WHEN 'on_vessel'        THEN 'active'
                    WHEN 'in_transshipment' THEN 'active'
                    WHEN 'vessel_arrived'   THEN 'active'
                    WHEN 'out_for_delivery' THEN 'out_for_delivery'
                    WHEN 'delivered'        THEN 'delivered'
                    WHEN 'completed'        THEN 'completed'
                    ELSE NULL
                END,
                new_status_new = CASE new_status
                    WHEN 'draft'            THEN 'draft'
                    WHEN 'in_transit'       THEN 'active'
                    WHEN 'at_port'          THEN 'active'
                    WHEN 'on_vessel'        THEN 'active'
                    WHEN 'in_transshipment' THEN 'active'
                    WHEN 'vessel_arrived'   THEN 'active'
                    WHEN 'out_for_delivery' THEN 'out_for_delivery'
                    WHEN 'delivered'        THEN 'delivered'
                    WHEN 'completed'        THEN 'completed'
                    ELSE NULL
                END
        ");

        // Step 3: Drop old enum columns
        Schema::table('trip_events', function (Blueprint $table) {
            $table->dropColumn(['previous_status', 'new_status']);
        });

        // Step 4: Add new enum columns
        // Note: NO ->after() that references just-dropped columns.
        // Columns will be added at the end; that's fine for functionality.
        Schema::table('trip_events', function (Blueprint $table) {
            $table->enum('previous_status', ['draft', 'active', 'out_for_delivery', 'delivered', 'completed'])
                ->nullable();
            $table->enum('new_status', ['draft', 'active', 'out_for_delivery', 'delivered', 'completed'])
                ->nullable();
        });

        // Step 5: Copy from temp
        DB::statement("UPDATE trip_events SET previous_status = previous_status_new, new_status = new_status_new");

        // Step 6: Drop temp columns
        Schema::table('trip_events', function (Blueprint $table) {
            $table->dropColumn(['previous_status_new', 'new_status_new']);
        });
    }

    public function down(): void
    {
        // Trips
        Schema::table('trips', function (Blueprint $table) {
            $table->string('status_old', 30)->nullable()->after('status');
        });

        DB::statement("
            UPDATE trips SET status_old = CASE status
                WHEN 'draft'            THEN 'draft'
                WHEN 'active'           THEN 'in_transit'
                WHEN 'out_for_delivery' THEN 'out_for_delivery'
                WHEN 'delivered'        THEN 'delivered'
                WHEN 'completed'        THEN 'completed'
                ELSE 'in_transit'
            END
        ");

        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->enum('status', ['draft', 'in_transit', 'at_port', 'on_vessel', 'in_transshipment', 'vessel_arrived', 'out_for_delivery', 'delivered', 'completed'])
                ->default('draft');
        });

        DB::statement("UPDATE trips SET status = status_old");

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('status_old');
            $table->index(['customer_id', 'status']);
        });

        // Trip events (best-effort rollback)
        Schema::table('trip_events', function (Blueprint $table) {
            $table->string('ps_old', 30)->nullable();
            $table->string('ns_old', 30)->nullable();
        });

        DB::statement("UPDATE trip_events SET ps_old = previous_status, ns_old = new_status");

        Schema::table('trip_events', function (Blueprint $table) {
            $table->dropColumn(['previous_status', 'new_status']);
        });

        Schema::table('trip_events', function (Blueprint $table) {
            $table->enum('previous_status', ['draft', 'in_transit', 'at_port', 'on_vessel', 'in_transshipment', 'vessel_arrived', 'out_for_delivery', 'delivered', 'completed'])->nullable();
            $table->enum('new_status', ['draft', 'in_transit', 'at_port', 'on_vessel', 'in_transshipment', 'vessel_arrived', 'out_for_delivery', 'delivered', 'completed'])->nullable();
        });

        DB::statement("UPDATE trip_events SET previous_status = ps_old, new_status = ns_old");

        Schema::table('trip_events', function (Blueprint $table) {
            $table->dropColumn(['ps_old', 'ns_old']);
        });
    }
};
