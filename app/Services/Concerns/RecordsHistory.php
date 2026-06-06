<?php

namespace App\Services\Concerns;

use App\Models\CustomerOnboardingHistory;
use App\Models\CustomerSepioHistory;
use App\Models\SealOrderHistory;

/**
 * All three lifecycle history tables (onboarding, sepio, seal-order) share the
 * same minimal shape:
 *
 *   - actor_type ∈ {'user', 'system'}
 *   - actor_id   = user id when actor_type='user'; null when 'system'
 *
 * Callers always pass a `User` (or null for system events) — we derive the
 * type from that, which is impossible to get wrong.
 */
trait RecordsHistory
{
    protected function recordOnboardingHistory(
        int     $customerId,
        ?string $fromStatus,
        string  $toStatus,
        ?int    $actorId = null,
        ?string $remarks = null,
        ?string $fileUrl = null,
    ): void
    {
        CustomerOnboardingHistory::create([
            'customer_id' => $customerId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorId ? 'user' : 'system',
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'remarks_file_url' => $fileUrl,
        ]);
    }

    protected function recordSepioHistory(
        int     $customerId,
        ?string $fromStatus,
        string  $toStatus,
        ?int    $actorId = null,
        ?string $remarks = null,
        ?array  $rejectedDocuments = null,
    ): void
    {
        CustomerSepioHistory::create([
            'customer_id' => $customerId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorId ? 'user' : 'system',
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'rejected_documents' => $rejectedDocuments,
        ]);
    }

    protected function recordOrderHistory(
        int     $orderId,
        ?string $fromStatus,
        string  $toStatus,
        ?int    $actorId = null,
        ?string $remarks = null,
        ?string $fileUrl = null,
    ): void
    {
        SealOrderHistory::create([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorId ? 'user' : 'system',
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'remarks_file_url' => $fileUrl,
        ]);
    }
}
