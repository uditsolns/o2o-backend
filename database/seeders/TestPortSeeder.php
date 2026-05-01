<?php

namespace Database\Seeders;

use App\Enums\PortCategory;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestPortSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@admin.com')->value('id');

        foreach ($this->portData() as $data) {
            Port::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'country' => $data['country'] ?? 'India',
                    'is_active' => true,
                    'created_by_id' => $adminId,
                    'sepio_id' => rand(100, 999),
                    'geo_fence_radius' => $data['geo_fence_radius'] ?? 2000,
                ])
            );
        }

        $this->command->info('  TestPortSeeder: ' . count($this->portData()) . ' ports seeded.');
    }

    private function portData(): array
    {
        return [
            // ── Indian Sea Ports ──────────────────────────────────────────────
            [
                'name' => 'Jawaharlal Nehru Port (JNPT)',
                'code' => 'INNSA',
                'city' => 'Navi Mumbai',
                'country' => 'India',
                'port_category' => PortCategory::Port,
                'lat' => 18.9488,
                'lng' => 72.9511,
                'geo_fence_radius' => 3000,
            ],
            [
                'name' => 'Mundra Port (APSEZ)',
                'code' => 'INMUN',
                'city' => 'Kutch',
                'country' => 'India',
                'port_category' => PortCategory::Port,
                'lat' => 22.8381,
                'lng' => 69.7032,
                'geo_fence_radius' => 3000,
            ],
            [
                'name' => 'Chennai Port (Kamarajar Port)',
                'code' => 'INMAA',
                'city' => 'Chennai',
                'country' => 'India',
                'port_category' => PortCategory::Port,
                'lat' => 13.0836,
                'lng' => 80.2969,
                'geo_fence_radius' => 3000,
            ],
            [
                'name' => 'Visakhapatnam Port',
                'code' => 'INVTZ',
                'city' => 'Visakhapatnam',
                'country' => 'India',
                'port_category' => PortCategory::Port,
                'lat' => 17.6868,
                'lng' => 83.2185,
                'geo_fence_radius' => 3000,
            ],
            [
                'name' => 'Cochin Port (ICTT)',
                'code' => 'INCOK',
                'city' => 'Kochi',
                'country' => 'India',
                'port_category' => PortCategory::Port,
                'lat' => 9.9312,
                'lng' => 76.2673,
                'geo_fence_radius' => 3000,
            ],

            // ── Indian ICDs ───────────────────────────────────────────────────
            [
                'name' => 'ICD Tughlakabad',
                'code' => 'INTDL',
                'city' => 'New Delhi',
                'country' => 'India',
                'port_category' => PortCategory::Icd,
                'lat' => 28.5011,
                'lng' => 77.2877,
                'geo_fence_radius' => 2000,
            ],
            [
                'name' => 'ICD Patparganj',
                'code' => 'INTDL4',
                'city' => 'New Delhi',
                'country' => 'India',
                'port_category' => PortCategory::Icd,
                'lat' => 28.6213,
                'lng' => 77.3156,
                'geo_fence_radius' => 2000,
            ],
            [
                'name' => 'ICD Whitefield',
                'code' => 'INBLR4',
                'city' => 'Bengaluru',
                'country' => 'India',
                'port_category' => PortCategory::Icd,
                'lat' => 12.9694,
                'lng' => 77.7480,
                'geo_fence_radius' => 2000,
            ],
            [
                'name' => 'ICD Pune (Dhanori)',
                'code' => 'INPNE6',
                'city' => 'Pune',
                'country' => 'India',
                'port_category' => PortCategory::Icd,
                'lat' => 18.5965,
                'lng' => 73.9034,
                'geo_fence_radius' => 2000,
            ],
            [
                'name' => 'ICD Tondiarpet Chennai',
                'code' => 'INMAA4',
                'city' => 'Chennai',
                'country' => 'India',
                'port_category' => PortCategory::Icd,
                'lat' => 13.1156,
                'lng' => 80.1551,
                'geo_fence_radius' => 2000,
            ],

            // ── Indian CFS ────────────────────────────────────────────────────
            [
                'name' => 'CFS Gateway Terminals India (JNPT)',
                'code' => 'CFSGTIL',
                'city' => 'Navi Mumbai',
                'country' => 'India',
                'port_category' => PortCategory::Cfs,
                'lat' => 18.9450,
                'lng' => 72.9600,
                'geo_fence_radius' => 1500,
            ],
            [
                'name' => 'CFS Balmer Lawrie (Chennai)',
                'code' => 'CFSBLAW',
                'city' => 'Chennai',
                'country' => 'India',
                'port_category' => PortCategory::Cfs,
                'lat' => 13.0800,
                'lng' => 80.2850,
                'geo_fence_radius' => 1500,
            ],
            [
                'name' => 'CFS Mundra (APSEZ Inland)',
                'code' => 'CFSMUN',
                'city' => 'Kutch',
                'country' => 'India',
                'port_category' => PortCategory::Cfs,
                'lat' => 22.8450,
                'lng' => 69.7150,
                'geo_fence_radius' => 1500,
            ],
        ];
    }
}
