<?php

namespace App\Services\Concerns;

use App\Models\CustomerOnboardingHistory;
use App\Models\CustomerSepioHistory;
use App\Models\SealOrderHistory;

trait RecordsHistory
{
    protected function recordOnboardingHistory(
        int     $customerId,
        ?string $fromStatus,
        string  $toStatus,
        string  $actorType,
        ?int    $actorId = null,
        ?string $remarks = null,
        ?string $fileUrl = null,
    ): void
    {
        CustomerOnboardingHistory::create([
            'customer_id' => $customerId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'remarks_file_url' => $fileUrl,
        ]);
    }

    protected function recordSepioHistory(
        int     $customerId,
        ?string $fromStatus,
        string  $toStatus,
        string  $triggeredByType,
        ?int    $triggeredById = null,
        ?string $remarks = null,
        ?array  $rejectedDocuments = null,
    ): void
    {
        CustomerSepioHistory::create([
            'customer_id' => $customerId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'triggered_by_type' => $triggeredByType,
            'triggered_by_id' => $triggeredById,
            'remarks' => $remarks,
            'rejected_documents' => $rejectedDocuments,
        ]);
    }

    protected function recordOrderHistory(
        int     $orderId,
        ?string $fromStatus,
        string  $toStatus,
        string  $actorType,
        ?int    $actorId = null,
        ?string $remarks = null,
        ?string $fileUrl = null,
    ): void
    {
        SealOrderHistory::create([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'remarks_file_url' => $fileUrl,
        ]);
    }
}
