<?php

namespace Database\Seeders;

use App\Enums\SealOrderStatus;
use App\Models\SealOrder;
use App\Models\SealOrderHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds seal_order_history rows so each SealOrder has a populated audit trail.
 *
 * Statuses represented and their progression paths:
 *
 *   il_pending → il_parked
 *   il_pending → il_approved
 *   il_pending → il_rejected
 *   il_parked  → il_approved
 *   il_parked  → il_rejected
 *   il_approved → mfg_pending → in_progress → order_placed → mfg_completed
 *                                       └→ mfg_rejected
 *   mfg_completed → in_transit → completed
 *   il_approved → in_transit  (some orders skip the explicit mfg steps)
 *
 * Cash payment confirmation is recorded as a same-status informational row
 *   (matches SealOrderService::markCashPaymentReceived behavior).
 */
class SealOrderHistorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->command->warn('  Skipping SealOrderHistorySeeder — admin user not found.');
            return;
        }

        $orders = SealOrder::with('customer')->orderBy('id')->get();
        $count = 0;

        foreach ($orders as $order) {
            if (SealOrderHistory::where('order_id', $order->id)->exists()) {
                continue;
            }

            $rows = $this->historyRowsFor($order, $admin->id);
            foreach ($rows as $row) {
                SealOrderHistory::insert([
                    'order_id' => $order->id,
                    'from_status' => $row['from_status'],
                    'to_status' => $row['to_status'],
                    'actor_type' => $row['actor_type'],
                    'actor_id' => $row['actor_id'],
                    'remarks' => $row['remarks'],
                    'remarks_file_url' => $row['remarks_file_url'] ?? null,
                    'created_at' => $row['created_at'],
                ]);
                $count++;
            }
        }

        $this->command->info("  SealOrderHistorySeeder: {$count} history rows seeded.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRowsFor(SealOrder $order, int $adminId): array
    {
        $customerUser = User::where('customer_id', $order->customer_id)->first();
        $customerActorId = $customerUser?->id ?? $adminId;
        $ilActorId = $order->il_approved_by ?? $adminId;

        $orderedAt = $order->ordered_at ?? ($order->created_at ?? now()->subDays(20));
        $ilAt = $order->il_approved_at ?? $orderedAt->copy()->addDays(2);
        $dispatchAt = $order->seals_dispatched_at ?? $orderedAt->copy()->addDays(8);
        $deliveredAt = $order->seals_delivered_at ?? $orderedAt->copy()->addDays(11);

        $current = $order->status instanceof SealOrderStatus
            ? $order->status->value
            : $order->status;

        $rows = [];

        // 1. Order placed
        $rows[] = [
            'from_status' => null,
            'to_status' => SealOrderStatus::IlPending->value,
            'actor_type' => 'customer',
            'actor_id' => $customerActorId,
            'remarks' => 'Order placed.',
            'created_at' => $orderedAt,
        ];

        // 2. From il_pending → {current state}
        switch ($current) {

            case SealOrderStatus::IlPending->value:
                // nothing further
                break;

            case SealOrderStatus::IlParked->value:
                $rows[] = [
                    'from_status' => SealOrderStatus::IlPending->value,
                    'to_status' => SealOrderStatus::IlParked->value,
                    'actor_type' => 'platform',
                    'actor_id' => $ilActorId,
                    'remarks' => $order->il_remarks ?: 'Parked pending additional verification.',
                    'created_at' => $ilAt,
                ];
                break;

            case SealOrderStatus::IlRejected->value:
                $rows[] = [
                    'from_status' => SealOrderStatus::IlPending->value,
                    'to_status' => SealOrderStatus::IlRejected->value,
                    'actor_type' => 'platform',
                    'actor_id' => $ilActorId,
                    'remarks' => $order->il_remarks ?: 'Order rejected.',
                    'created_at' => $ilAt,
                ];
                break;

            case SealOrderStatus::IlApproved->value:
            case SealOrderStatus::MfgPending->value:
            case SealOrderStatus::InProgress->value:
            case SealOrderStatus::OrderPlaced->value:
            case SealOrderStatus::InTransit->value:
            case SealOrderStatus::MfgCompleted->value:
            case SealOrderStatus::Completed->value:
                $rows[] = [
                    'from_status' => SealOrderStatus::IlPending->value,
                    'to_status' => SealOrderStatus::IlApproved->value,
                    'actor_type' => 'platform',
                    'actor_id' => $ilActorId,
                    'remarks' => $order->il_remarks ?: 'Approved. Forwarded to Sepio for manufacturing.',
                    'created_at' => $ilAt,
                ];

                if (in_array($current, [
                    SealOrderStatus::MfgPending->value,
                    SealOrderStatus::InProgress->value,
                    SealOrderStatus::OrderPlaced->value,
                    SealOrderStatus::InTransit->value,
                    SealOrderStatus::MfgCompleted->value,
                    SealOrderStatus::Completed->value,
                ], true)) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::IlApproved->value,
                        'to_status' => SealOrderStatus::MfgPending->value,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'remarks' => 'Manufacturing slot reserved with Sepio.',
                        'created_at' => $ilAt->copy()->addDays(1),
                    ];
                }

                if (in_array($current, [
                    SealOrderStatus::InProgress->value,
                    SealOrderStatus::OrderPlaced->value,
                    SealOrderStatus::InTransit->value,
                    SealOrderStatus::MfgCompleted->value,
                    SealOrderStatus::Completed->value,
                ], true)) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::MfgPending->value,
                        'to_status' => SealOrderStatus::InProgress->value,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'remarks' => 'Production in progress at Sepio.',
                        'created_at' => $ilAt->copy()->addDays(3),
                    ];
                }

                if (in_array($current, [
                    SealOrderStatus::OrderPlaced->value,
                    SealOrderStatus::InTransit->value,
                    SealOrderStatus::MfgCompleted->value,
                    SealOrderStatus::Completed->value,
                ], true)) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::InProgress->value,
                        'to_status' => SealOrderStatus::OrderPlaced->value,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'remarks' => 'Order placed with shipping line / courier.',
                        'created_at' => $ilAt->copy()->addDays(5),
                    ];
                }

                if (in_array($current, [
                    SealOrderStatus::MfgCompleted->value,
                    SealOrderStatus::InTransit->value,
                    SealOrderStatus::Completed->value,
                ], true)) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::OrderPlaced->value,
                        'to_status' => SealOrderStatus::MfgCompleted->value,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'remarks' => 'Manufacturing completed; seals ready for dispatch.',
                        'created_at' => $ilAt->copy()->addDays(7),
                    ];
                }

                if (in_array($current, [SealOrderStatus::InTransit->value, SealOrderStatus::Completed->value], true)) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::MfgCompleted->value,
                        'to_status' => SealOrderStatus::InTransit->value,
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'remarks' => 'Seals dispatched. Courier: ' . ($order->courier_name ?? 'N/A') . ' — Docket: ' . ($order->courier_docket_number ?? 'N/A') . '.',
                        'created_at' => $dispatchAt,
                    ];
                }

                if ($current === SealOrderStatus::Completed->value) {
                    $rows[] = [
                        'from_status' => SealOrderStatus::InTransit->value,
                        'to_status' => SealOrderStatus::Completed->value,
                        'actor_type' => 'customer',
                        'actor_id' => $customerActorId,
                        'remarks' => 'Seals received and ingested into inventory.',
                        'created_at' => $deliveredAt,
                    ];
                }
                break;

            case SealOrderStatus::MfgRejected->value:
                $rows[] = [
                    'from_status' => SealOrderStatus::IlPending->value,
                    'to_status' => SealOrderStatus::IlApproved->value,
                    'actor_type' => 'platform',
                    'actor_id' => $ilActorId,
                    'remarks' => 'Approved. Forwarded to Sepio for manufacturing.',
                    'created_at' => $ilAt,
                ];
                $rows[] = [
                    'from_status' => SealOrderStatus::IlApproved->value,
                    'to_status' => SealOrderStatus::MfgPending->value,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'remarks' => 'Manufacturing slot reserved with Sepio.',
                    'created_at' => $ilAt->copy()->addDays(1),
                ];
                $rows[] = [
                    'from_status' => SealOrderStatus::MfgPending->value,
                    'to_status' => SealOrderStatus::MfgRejected->value,
                    'actor_type' => 'platform',
                    'actor_id' => $ilActorId,
                    'remarks' => $order->il_remarks ?: 'Sepio manufacturer rejected the order.',
                    'created_at' => $ilAt->copy()->addDays(4),
                ];
                break;
        }

        // 3. Cash payment confirmation (informational, same status → same status)
        if ($order->payment_type === 'cash' && in_array($current, [
            SealOrderStatus::InTransit->value,
            SealOrderStatus::MfgCompleted->value,
            SealOrderStatus::Completed->value,
        ], true)) {
            $rows[] = [
                'from_status' => $current,
                'to_status' => $current,
                'actor_type' => 'platform',
                'actor_id' => $ilActorId,
                'remarks' => 'Cash payment confirmed as received.',
                'created_at' => $dispatchAt->copy()->addHours(6),
            ];
        }

        return $rows;
    }
}
