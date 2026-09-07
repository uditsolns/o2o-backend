<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Sepio\SepioOnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SepioUpdateCompanyDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 30;

    public function __construct(private readonly Customer $customer)
    {
    }

    public function handle(SepioOnboardingService $service): void
    {
        $service->updateCompanyDetails($this->customer->fresh());
    }
}
