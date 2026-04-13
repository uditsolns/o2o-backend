<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\SealOrderStatus;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\CustomerPort;
use App\Models\SealOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds SealOrders covering every SealOrderStatus value.
 *
 * Only targets Completed customers (they have wallets, locations, ports).
 *
 * Returns all seeded orders so SealSeeder can ingest seals for Completed ones.
 *
 * @return SealOrder[]
 */
class SealOrderSeeder extends Seeder
{
    public function run(): array
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();

        $allOrders = [];
        $total = 0;

        foreach ($customers as $customer) {
            $user = User::where('customer_id', $customer->id)->firstOrFail();
            $wallet = $customer->wallet;
            $locations = CustomerLocation::where('customer_id', $customer->id)->get();
            $ports = CustomerPort::where('customer_id', $customer->id)->get();

            if (!$wallet || $locations->isEmpty() || $ports->isEmpty()) {
                $this->command->warn("  Skipping orders for customer #{$customer->id} — missing wallet/location/ports.");
                continue;
            }

            $billingLoc = $locations->first(fn($l) => !empty($l->sepio_billing_address_id));
            $shippingLoc = $locations->first(fn($l) => !empty($l->sepio_shipping_address_id));

            if (!$billingLoc || !$shippingLoc) continue;

            $portCodes = $ports->pluck('code')->take(2)->all();

            $orderDefs = $this->orderDefinitions($customer, $user, $admin, $billingLoc, $shippingLoc, $portCodes, $wallet);

            foreach ($orderDefs as $def) {
                // Prevent duplicate order_refs on re-run
                $order = SealOrder::firstOrCreate(
                    ['order_ref' => $def['order_ref']],
                    $def
                );
                $allOrders[] = $order;
                $total++;
            }
        }

        $this->command->info("  SealOrderSeeder: {$total} orders seeded.");

        return $allOrders;
    }

    // ── Order definitions ─────────────────────────────────────────────────────

    private function orderDefinitions(
        Customer $customer, User $user, User $admin,
                 $billingLoc, $shippingLoc,
        array    $portCodes, $wallet
    ): array
    {
        $base = [
            'customer_id' => $customer->id,
            'billing_location_id' => $billingLoc->id,
            'shipping_location_id' => $shippingLoc->id,
            'receiver_name' => $customer->primary_contact_name,
            'receiver_contact' => $customer->primary_contact_mobile,
            'sepio_order_ports' => $portCodes,
        ];

        $unitPrice = 165.00;
        $freightRate = (float)$wallet->freight_rate_per_seal;
        $gstRate = 0.18;

        $calc = function (int $qty) use ($unitPrice, $freightRate, $gstRate) {
            $sealCost = round($qty * $unitPrice, 2);
            $freightAmount = round($qty * $freightRate, 2);
            $gstAmount = round(($sealCost + $freightAmount) * $gstRate, 2);
            return [
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'seal_cost' => $sealCost,
                'freight_amount' => $freightAmount,
                'gst_amount' => $gstAmount,
                'total_amount' => round($sealCost + $freightAmount + $gstAmount, 2),
            ];
        };

        $cid = $customer->id;

        return [
            // IL Pending — just placed
            array_merge($base, $calc(50), [
                'order_ref' => "IL{$cid}T001",
                'ordered_by_id' => $user->id,
                'payment_type' => 'cash',
                'status' => SealOrderStatus::IlPending,
            ]),

            // IL Parked
            array_merge($base, $calc(100), [
                'order_ref' => "IL{$cid}T002",
                'ordered_by_id' => $user->id,
                'payment_type' => 'cash',
                'status' => SealOrderStatus::IlParked,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(3),
                'il_remarks' => 'Awaiting shipping address verification.',
            ]),

            // IL Approved — forwarded to Sepio
            array_merge($base, $calc(200), [
                'order_ref' => "IL{$cid}T003",
                'ordered_by_id' => $user->id,
                'payment_type' => 'cash',
                'status' => SealOrderStatus::IlApproved,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(5),
                'il_remarks' => 'Approved. Forwarded to Sepio.',
                'sepio_order_id' => 'SEPIO-ORD-' . $cid . '-003',
                'sepio_billing_address_id' => $billingLoc->sepio_billing_address_id,
                'sepio_shipping_address_id' => $shippingLoc->sepio_shipping_address_id,
            ]),

            // Mfg Pending
            array_merge($base, $calc(500), [
                'order_ref' => "IL{$cid}T004",
                'ordered_by_id' => $user->id,
                'payment_type' => 'credit',
                'status' => SealOrderStatus::MfgPending,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(8),
                'sepio_order_id' => 'SEPIO-ORD-' . $cid . '-004',
                'sepio_billing_address_id' => $billingLoc->sepio_billing_address_id,
                'sepio_shipping_address_id' => $shippingLoc->sepio_shipping_address_id,
            ]),

            // In Transit (seals dispatched from Sepio, not yet ingested)
            array_merge($base, $calc(100), [
                'order_ref' => "IL{$cid}T005",
                'ordered_by_id' => $user->id,
                'payment_type' => 'advance_balance',
                'status' => SealOrderStatus::InTransit,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(12),
                'sepio_order_id' => 'SEPIO-ORD-' . $cid . '-005',
                'sepio_billing_address_id' => $billingLoc->sepio_billing_address_id,
                'sepio_shipping_address_id' => $shippingLoc->sepio_shipping_address_id,
                'courier_name' => 'Blue Dart',
                'courier_docket_number' => 'BD' . rand(10000000, 99999999),
                'seals_dispatched_at' => now()->subDays(7),
            ]),

            // Completed — seals ingested and delivered
            array_merge($base, $calc(200), [
                'order_ref' => "IL{$cid}T006",
                'ordered_by_id' => $user->id,
                'payment_type' => 'cash',
                'status' => SealOrderStatus::Completed,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(20),
                'sepio_order_id' => 'SEPIO-ORD-' . $cid . '-006',
                'sepio_billing_address_id' => $billingLoc->sepio_billing_address_id,
                'sepio_shipping_address_id' => $shippingLoc->sepio_shipping_address_id,
                'courier_name' => 'DTDC',
                'courier_docket_number' => 'DTDC' . rand(10000000, 99999999),
                'seals_dispatched_at' => now()->subDays(15),
                'seals_delivered_at' => now()->subDays(12),
            ]),

            // IL Rejected
            array_merge($base, $calc(50), [
                'order_ref' => "IL{$cid}T007",
                'ordered_by_id' => $user->id,
                'payment_type' => 'cash',
                'status' => SealOrderStatus::IlRejected,
                'il_approved_by' => $admin->id,
                'il_approved_at' => now()->subDays(6),
                'il_remarks' => 'Quantity below minimum threshold for this payment type.',
            ]),
        ];
    }
}
