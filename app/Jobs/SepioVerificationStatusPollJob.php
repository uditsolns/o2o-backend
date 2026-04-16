<?php

namespace App\Jobs;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Services\Sepio\SepioClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SepioVerificationStatusPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(SepioClient $client): void
    {
        // All customers registered on Sepio but not yet fully verified
        $customers = Customer::whereNotNull('sepio_company_id')
            ->whereIn('onboarding_status', [
                CustomerOnboardingStatus::IlApproved,
                CustomerOnboardingStatus::MfgRejected,
            ])
            ->get();

        if ($customers->isEmpty()) return;

        // Log::info('customers', $customers->toArray());

        // Sepio allows max 100 per request — chunk accordingly
        $customers->chunk(100)->each(function ($chunk) use ($client) {
            try {
                $this->pollChunk($client, $chunk);
            } catch (\Throwable $e) {
                Log::error('SepioVerificationStatusPollJob: chunk failed', [
                    'company_ids' => $chunk->pluck('sepio_company_id')->all(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function pollChunk(SepioClient $client, Collection $chunk): void
    {
        $response = $client->postAs($chunk->first(), '/api/v1/document/verification/status/pull', [
            'requestId' => 'ILGIC-' . now()->timestamp,
            'companyIds' => $chunk->pluck('sepio_company_id')->all(),
        ]);

        if ($response->failed()) {
            $msg = $client->parseError($response, 'Verification poll failed.');
            Log::error('Sepio verification poll failed', ['error' => $msg]);
            return;
        }

        $results = $response->json('results', []);
        $map = $chunk->keyBy('sepio_company_id');

        foreach ($results as $result) {
            $customer = $map[$result['companyId']] ?? null;
            if (!$customer) continue;

            try {
                match ($result['verificationStatus']) {
                    'VERIFIED' => $this->markCompleted($customer),
                    'REJECTED' => $this->markRejected($customer, $result),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::error('SepioVerificationStatusPollJob: result processing failed', [
                    'customer_id' => $customer->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markCompleted(Customer $customer): void
    {
        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::Completed,
        ]);

        Log::info('Customer Sepio verification completed', ['customer_id' => $customer->id]);
    }

    private function markRejected(Customer $customer, array $result): void
    {
        $rejected = implode(', ', $result['rejectedDocuments'] ?? []);

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::MfgRejected,
            'il_remarks' => "Sepio rejected documents: {$rejected}",
        ]);

        Log::warning('Customer Sepio verification rejected', [
            'customer_id' => $customer->id,
            'rejected_documents' => $result['rejectedDocuments'],
        ]);
    }
}
