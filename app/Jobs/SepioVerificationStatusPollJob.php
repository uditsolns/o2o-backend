<?php

namespace App\Jobs;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\SepioStatus;
use App\Models\Customer;
use App\Models\CustomerSepioHistory;
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
        $customers = Customer::where('sepio_enabled', true)
            ->whereIn('sepio_status', [
                SepioStatus::VerificationPending->value,
            ])
            ->whereNotNull('sepio_company_id')
            ->get();

        if ($customers->isEmpty()) return;

        $customers->chunk(100)->each(function ($chunk) use ($client) {
            try {
                $this->pollChunk($client, $chunk);
            } catch (\Throwable $e) {
                Log::error('SepioVerificationStatusPollJob: chunk failed', [
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
        $fromStatus = $customer->sepio_status->value;

        $customer->update([
            'sepio_status' => SepioStatus::Verified,
            'onboarding_status' => CustomerOnboardingStatus::Completed,
        ]);

        CustomerSepioHistory::create([
            'customer_id' => $customer->id,
            'from_status' => $fromStatus,
            'to_status' => SepioStatus::Verified->value,
            'triggered_by_type' => 'job',
            'remarks' => 'Sepio document verification passed.',
        ]);

        Log::info('Customer Sepio verification completed', ['customer_id' => $customer->id]);
    }

    private function markRejected(Customer $customer, array $result): void
    {
        $fromStatus = $customer->sepio_status->value;
        $rejectedDocuments = $result['rejectedDocuments'] ?? [];
        $rejected = implode(', ', $rejectedDocuments);

        // Mark individual documents with rejection reason
        foreach ($rejectedDocuments as $rejectedDocType) {
            $customer->documents()
                ->where('doc_type', $rejectedDocType)
                ->update(['sepio_rejection_reason' => 'Rejected by Sepio during verification.']);
        }

        $customer->update([
            'sepio_status' => SepioStatus::Rejected,
            'il_remarks' => "Sepio rejected documents: {$rejected}",
        ]);

        CustomerSepioHistory::create([
            'customer_id' => $customer->id,
            'from_status' => $fromStatus,
            'to_status' => SepioStatus::Rejected->value,
            'triggered_by_type' => 'job',
            'remarks' => "Sepio rejected the following documents: {$rejected}",
            'rejected_documents' => $rejectedDocuments,
        ]);

        Log::warning('Customer Sepio verification rejected', [
            'customer_id' => $customer->id,
            'rejected_documents' => $rejectedDocuments,
        ]);
    }
}
