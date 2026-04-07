<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Services\Sepio\SepioClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedSepioPortsCommand extends Command
{
    protected $signature = 'sepio:seed-ports {--fresh : Truncate and re-seed all ports}';
    protected $description = 'Seed ports, ICDs and CFS locations from Sepio master lists';

    public function handle(SepioClient $client): int
    {
        $this->info('Fetching Sepio port masters...');

        $sources = [
            'port' => '/customsExecutive/customsportlist',
            'icd' => '/customsExecutive/customsicdlist',
            'cfs' => '/companyAdmin/cfslocationlist',
        ];

        if ($this->option('fresh')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Port::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->warn('Truncated ports table.');
        }

        $total = 0;

        foreach ($sources as $category => $endpoint) {
            $response = $client->get($endpoint);

            if ($response->failed()) {
                $this->error("Failed to fetch {$category}: " . $response->body());
                continue;
            }

            $items = $response->json('data', []);

            foreach ($items as $item) {
                // itemName format: "JNCH (INNSA1)" — parse code from parentheses
                preg_match('/\(([^)]+)\)/', $item['itemName'], $matches);
                $code = $matches[1] ?? null;
                $name = trim(preg_replace('/\s*\([^)]*\)/', '', $item['itemName']));

                if (!$code) {
                    $this->warn("Skipping item with unparseable code: {$item['itemName']}");
                    continue;
                }

                Port::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'port_category' => $category,
                        'sepio_id' => $item['id'],
                        'country' => 'India',
                        'is_active' => true,
                    ]
                );

                $total++;
            }

            $this->info("  {$category}: " . count($items) . ' records synced.');
        }

        $this->info("Done. {$total} ports upserted.");

        return self::SUCCESS;
    }
}
