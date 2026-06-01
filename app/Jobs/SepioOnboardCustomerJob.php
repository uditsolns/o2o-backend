<?php

namespace App\Jobs;

use App\Enums\SepioStatus;
use App\Exceptions\SepioException;
use App\Models\Customer;
use App\Models\CustomerSepioHistory;
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
        $customer = $this->customer->fresh(['ports', 'locations', 'documents']);

        // Step 1 — Register company
        try {
            $service->registerCompany($customer);
            $customer->refresh();

            $this->writeHistory($customer, SepioStatus::Pending->value, SepioStatus::Registered->value, 'Company registered on Sepio.');
            $customer->update(['sepio_status' => SepioStatus::Registered]);
        } catch (SepioException $e) {
            Log::error('SepioOnboardCustomerJob: registerCompany failed', [
                'customer_id' => $customer->id, 'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $customer->refresh();

        // Step 2 — Sync locations
        try {
            $service->syncAllLocations($customer);
        } catch (\Throwable $e) {
            Log::error('SepioOnboardCustomerJob: syncAllLocations failed', [
                'customer_id' => $customer->id, 'error' => $e->getMessage(),
            ]);
        }

        // Step 3 — Upload documents
        try {
            $service->uploadAllDocuments($customer);
            $this->writeHistory($customer, SepioStatus::Registered->value, SepioStatus::DocsUploaded->value, 'KYC documents uploaded.');
            $customer->update(['sepio_status' => SepioStatus::DocsUploaded]);
        } catch (\Throwable $e) {
            Log::error('SepioOnboardCustomerJob: uploadAllDocuments failed', [
                'customer_id' => $customer->id, 'error' => $e->getMessage(),
            ]);
        }

        // Step 4 — Await verification
        $this->writeHistory($customer, SepioStatus::DocsUploaded->value, SepioStatus::VerificationPending->value, 'Awaiting Sepio document verification.');
        $customer->update(['sepio_status' => SepioStatus::VerificationPending]);

        Log::info('SepioOnboardCustomerJob completed', ['customer_id' => $customer->id]);
    }

    private function writeHistory(Customer $customer, string $from, string $to, string $remarks): void
    {
        CustomerSepioHistory::create([
            'customer_id' => $customer->id,
            'from_status' => $from,
            'to_status' => $to,
            'triggered_by_type' => 'job',
            'triggered_by_id' => null,
            'remarks' => $remarks,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SepioOnboardCustomerJob failed', [
            'customer_id' => $this->customer->id,
            'error' => $e->getMessage(),
        ]);
    }
}
