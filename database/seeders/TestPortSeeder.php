<?php

namespace Database\Seeders;

use App\Enums\PortCategory;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a handful of dummy ports so that other seeders (CustomerPort,
 * CustomerRoute, Trip) can reference real port IDs.
 *
 * ⚠  In production, run `php artisan sepio:seed-ports` instead.
 *    This seeder is intentionally called ONLY from DummyDataSeeder.
 */
class TestPortSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@admin.com')->value('id');

        $ports = [
            // Sea Ports
            ['name' => 'Jawaharlal Nehru Port', 'code' => 'INNSA1', 'city' => 'Navi Mumbai', 'category' => PortCategory::Port],
            ['name' => 'Mundra Port', 'code' => 'INMUN1', 'city' => 'Kutch', 'category' => PortCategory::Port],
            ['name' => 'Chennai Port', 'code' => 'INMAA1', 'city' => 'Chennai', 'category' => PortCategory::Port],
            ['name' => 'Vizag Port', 'code' => 'INVTZ1', 'city' => 'Visakhapatnam', 'category' => PortCategory::Port],

            // ICDs
            ['name' => 'ICD Tughlakabad', 'code' => 'INTDL6', 'city' => 'New Delhi', 'category' => PortCategory::Icd],
            ['name' => 'ICD Patparganj', 'code' => 'INTDL4', 'city' => 'New Delhi', 'category' => PortCategory::Icd],
            ['name' => 'ICD Whitefield', 'code' => 'INBLR4', 'city' => 'Bengaluru', 'category' => PortCategory::Icd],
            ['name' => 'ICD Pune', 'code' => 'INPNE6', 'city' => 'Pune', 'category' => PortCategory::Icd],

            // CFS
            ['name' => 'CFS Gateway Terminals', 'code' => 'CFSGTIL', 'city' => 'Navi Mumbai', 'category' => PortCategory::Cfs],
            ['name' => 'CFS Balmer Lawrie', 'code' => 'CFSBLAW', 'city' => 'Chennai', 'category' => PortCategory::Cfs],
        ];

        foreach ($ports as $data) {
            Port::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'city' => $data['city'],
                    'country' => 'India',
                    'port_category' => $data['category'],
                    'sepio_id' => rand(100, 999),
                    'lat' => fake()->latitude(8, 35),
                    'lng' => fake()->longitude(68, 97),
                    'geo_fence_radius' => 2000,
                    'is_active' => true,
                    'created_by_id' => $adminId,
                ]
            );
        }

        $this->command->info('  TestPortSeeder: ' . count($ports) . ' ports seeded.');
    }
}
