<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Local development seeder chain.
 *
 * Wires the synthetic local data set that is safe to commit to the repo —
 * no real Sepio JWTs or encrypted credentials are seeded here. Stable
 * external identifiers (sepio_company_id, SPPL order ids, Kpler-style
 * tracking ids) are hardcoded as deterministic per-customer/per-trip
 * values so the frontend can be developed against a realistic URL shape.
 *
 * For a profile with real Sepio credentials, drop database/o2o.sql into
 * the project root and run:
 *
 *   php artisan migrate:fresh --seed --seeder=Database\\Seeders\\LiveSepioSeeder
 *
 * See LiveSepioSeeder for the full live-load flow.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱  Starting DatabaseSeeder (local-development profile)…');
        $this->command->info('');

        // ── Foundation: roles, permissions, platform admin user ─────────────
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);

        // Master data — ports are referenced by every customer trip.
        $this->call(TestPortSeeder::class);

        // ── Tenants: customers, ports, routes, locations, docs, consignors/consignees
        $this->call(CustomerSeeder::class);

        // Onboarding history must run BEFORE documents (documents depend on
        // the customer_admin user record created by the customer seeder).
        $this->call(CustomerDocumentSignatorySeeder::class);
        $this->call(CustomerLocationSeeder::class);
        $this->call(CustomerPortSeeder::class);
        $this->call(CustomerRouteSeeder::class);
        $this->call(CustomerConsignorConsigneeSeeder::class);

        // ── Customer lifecycle history (after customer is created) ──────────
        $this->call(CustomerOnboardingHistorySeeder::class);
        $this->call(CustomerSepioHistorySeeder::class);

        // ── Wallet + pricing + transactions ─────────────────────────────────
        $this->call(CustomerWalletSeeder::class);
        $this->call(TripPricingRuleSeeder::class);
        $this->call(WalletTransactionSeeder::class);

        // ── Seal orders, seals, seal history ────────────────────────────────
        $this->call(SealOrderSeeder::class);
        $this->call(SealOrderHistorySeeder::class);
        $this->call(SealSeeder::class);

        // ── Trips + tracking + milestones + events + documents ──────────────
        $this->call(TripSeeder::class);
        $this->call(TripSegmentSeeder::class);
        $this->call(TripTrackingPointSeeder::class);
        $this->call(TripContainerTrackingSeeder::class);
        $this->call(TripShipmentMilestoneSeeder::class);
        $this->call(TripEventSeeder::class);
        $this->call(TripDocumentSeeder::class);
        $this->call(SealStatusLogSeeder::class);

        $this->command->info('');
        $this->command->info('✅  DatabaseSeeder complete (local-development profile).');
    }
}
