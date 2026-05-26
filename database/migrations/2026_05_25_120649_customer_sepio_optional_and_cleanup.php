<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Step 1: Add new columns to customers ─────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('sepio_enabled')
                ->default(false)
                ->after('is_active')
                ->comment('Whether this customer has Sepio seal integration enabled');

            $table->enum('sepio_status', [
                'pending',
                'registered',
                'docs_uploaded',
                'verification_pending',
                'verified',
                'rejected',
            ])->nullable()
                ->after('sepio_enabled')
                ->comment('Tracks Sepio-specific lifecycle separately from platform onboarding');
        });

        // ── Step 2: Backfill sepio_enabled and sepio_status ──────────────────
        // Any customer already registered on Sepio keeps sepio_enabled = true
        DB::statement("
            UPDATE customers
            SET sepio_enabled = 1,
                sepio_status = CASE onboarding_status
                    WHEN 'completed'    THEN 'verified'
                    WHEN 'il_approved'  THEN 'verification_pending'
                    WHEN 'mfg_rejected' THEN 'rejected'
                    ELSE 'pending'
                END
            WHERE sepio_company_id IS NOT NULL
        ");

        // ── Step 3: Migrate mfg_rejected → il_approved in onboarding_status ──
        // mfg_rejected was a Sepio-specific state; the platform onboarding
        // for these customers was actually il_approved (Sepio just failed after)
        DB::statement("
            UPDATE customers
            SET onboarding_status = 'il_approved'
            WHERE onboarding_status = 'mfg_rejected'
        ");

        // ── Step 4: Non-Sepio customers that are il_approved → completed ──────
        // Platform approval is sufficient for completion if Sepio is not enabled
        DB::statement("
            UPDATE customers
            SET onboarding_status = 'completed'
            WHERE sepio_enabled = 0
              AND onboarding_status = 'il_approved'
        ");

        // ── Step 5: Drop removed profile columns ─────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'pan_number',
                'cin_number',
                'tin_number',
                'cha_number',
            ]);
        });

        // ── Step 6: Refactor onboarding_status enum (remove mfg_rejected) ────
        // MySQL ENUM alter: add temp column, copy, drop old, rename
        Schema::table('customers', function (Blueprint $table) {
            $table->string('onboarding_status_new', 30)->nullable()->after('onboarding_status');
        });

        DB::statement("UPDATE customers SET onboarding_status_new = onboarding_status");

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('onboarding_status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('onboarding_status', [
                'pending',
                'submitted',
                'il_parked',
                'il_approved',
                'il_rejected',
                'completed',
            ])->default('pending')->after('onboarding_status_new');
        });

        DB::statement("UPDATE customers SET onboarding_status = onboarding_status_new");

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('onboarding_status_new');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pan_number', 20)->nullable();
            $table->string('cin_number', 25)->nullable();
            $table->string('tin_number', 30)->nullable();
            $table->string('cha_number', 30)->nullable();
            $table->dropColumn(['sepio_enabled', 'sepio_status']);
        });

        // Restore mfg_rejected to enum (best-effort, data is lost)
        Schema::table('customers', function (Blueprint $table) {
            $table->string('onboarding_status_old', 30)->nullable();
        });
        DB::statement("UPDATE customers SET onboarding_status_old = onboarding_status");
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('onboarding_status');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('onboarding_status', [
                'pending', 'submitted', 'il_parked', 'il_approved',
                'il_rejected', 'mfg_rejected', 'completed',
            ])->default('pending');
        });
        DB::statement("UPDATE customers SET onboarding_status = onboarding_status_old");
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('onboarding_status_old');
        });
    }
};
