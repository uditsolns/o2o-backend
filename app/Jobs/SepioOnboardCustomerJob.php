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

        // The job is dispatched on initial Sepio enable AND on retry. Each step
        // here is idempotent: if the customer is already past it (e.g. retry
        // after only the doc upload failed), we skip the work and the history
        // entry to avoid the duplicate registration/upload trail we used to see.

        // Step 1 — Register company
        if (!$this->hasReached($customer, SepioStatus::Registered)) {
            try {
                $service->registerCompany($customer);
                $customer->refresh();

                $this->advance(
                    $customer,
                    SepioStatus::Registered,
                    'Company registered on Sepio.'
                );
            } catch (SepioException $e) {
                Log::error('SepioOnboardCustomerJob: registerCompany failed', [
                    'customer_id' => $customer->id, 'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $customer->refresh();

        // Step 2 — Sync locations (no status transition tied to this — it's a
        // best-effort side-effect that happens once per onboarding attempt)
        try {
            $service->syncAllLocations($customer);
        } catch (\Throwable $e) {
            Log::error('SepioOnboardCustomerJob: syncAllLocations failed', [
                'customer_id' => $customer->id, 'error' => $e->getMessage(),
            ]);
        }

        // Step 3 — Upload documents
        if (!$this->hasReached($customer, SepioStatus::DocsUploaded)) {
            try {
                $service->uploadAllDocuments($customer);
                $this->advance(
                    $customer,
                    SepioStatus::DocsUploaded,
                    'KYC documents uploaded.'
                );
            } catch (\Throwable $e) {
                Log::error('SepioOnboardCustomerJob: uploadAllDocuments failed', [
                    'customer_id' => $customer->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Step 4 — Await verification
        if (!$this->hasReached($customer, SepioStatus::VerificationPending)) {
            $this->advance(
                $customer,
                SepioStatus::VerificationPending,
                'Awaiting Sepio document verification.'
            );
        }

        Log::info('SepioOnboardCustomerJob completed', ['customer_id' => $customer->id]);
    }

    /**
     * True when the customer has already reached or passed the target status.
     * Uses ordinal position in the SepioStatus enum to compare progress.
     */
    private function hasReached(Customer $customer, SepioStatus $target): bool
    {
        $progression = [
            SepioStatus::Pending->value => 0,
            SepioStatus::Registered->value => 1,
            SepioStatus::DocsUploaded->value => 2,
            SepioStatus::VerificationPending->value => 3,
            SepioStatus::Verified->value => 4,
            SepioStatus::Rejected->value => 4, // terminal (peer of Verified)
        ];

        $current = $customer->sepio_status?->value;
        if ($current === null) {
            return false;
        }

        return ($progression[$current] ?? -1) >= ($progression[$target->value] ?? PHP_INT_MAX);
    }

    /**
     * Transition the customer to a new status, recording history exactly once.
     * Refreshes the in-memory model so subsequent hasReached() calls see the
     * new value.
     */
    private function advance(Customer $customer, SepioStatus $to, string $remarks): void
    {
        $from = $customer->sepio_status?->value;

        CustomerSepioHistory::create([
            'customer_id' => $customer->id,
            'from_status' => $from,
            'to_status' => $to->value,
            'actor_type' => 'system',
            'actor_id' => null,
            'remarks' => $remarks,
        ]);

        $customer->update(['sepio_status' => $to]);
        $customer->refresh();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SepioOnboardCustomerJob failed', [
            'customer_id' => $this->customer->id,
            'error' => $e->getMessage(),
        ]);
    }
}
