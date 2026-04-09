<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Sepio\SepioOnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SepioOnboardCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 120;

    public function __construct(private readonly Customer $customer)
    {
    }

    public function handle(SepioOnboardingService $service): void
    {
        $customer = $this->customer->fresh([
            'ports', 'locations', 'documents',
        ]);

        // Step 1 — Register company (unauthenticated)
        $service->registerCompany($customer);

        // Reload to get sepio_company_id + fresh credentials
        $customer->refresh();

        // Step 2 — Sync all locations as billing + shipping addresses
        $service->syncAllLocations($customer);

        // Step 3 — Upload all KYC documents
        $service->uploadAllDocuments($customer);

        Log::info('SepioOnboardCustomerJob completed', ['customer_id' => $customer->id]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SepioOnboardCustomerJob failed', [
            'customer_id' => $this->customer->id,
            'error' => $e->getMessage(),
        ]);
    }
}
