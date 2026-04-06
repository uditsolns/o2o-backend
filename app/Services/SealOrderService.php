<?php

namespace App\Services;

use App\Enums\SealOrderStatus;
use App\Enums\WalletCoastingType;
use App\Models\Customer;
use App\Models\CustomerPort;
use App\Models\SealOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

readonly class SealOrderService
{
    public function __construct(private WalletService $walletService)
    {
    }

    public function store(array $data, User $orderedBy): SealOrder
    {
        $customer = $orderedBy->customer;
        $wallet = $customer->wallet;

        abort_if(!$wallet, 422, 'Customer wallet has not been configured yet.');

        $unitPrice = $this->walletService->resolvePriceForQuantity($customer, $data['quantity']);
        abort_if(!$unitPrice, 422, 'No active pricing tier found for the requested quantity.');

        $this->assertPaymentViable($wallet, $data['payment_type'], $data['quantity'], $unitPrice);

        $sealCost = round($data['quantity'] * $unitPrice, 2);
        $freightAmount = round($data['quantity'] * $wallet->freight_rate_per_seal, 2);
        $gstAmount = round(($sealCost + $freightAmount) * 0.18, 2);
        $totalAmount = round($sealCost + $freightAmount + $gstAmount, 2);

        $ports = CustomerPort::whereIn('id', $data['port_ids'])
            ->where('customer_id', $customer->id)
            ->get();

        abort_if($ports->count() !== count($data['port_ids']), 422, 'One or more selected ports are invalid.');

        return DB::transaction(function () use ($data, $orderedBy, $customer, $wallet, $unitPrice, $sealCost, $freightAmount, $gstAmount, $totalAmount, $ports) {
            $order = SealOrder::create([
                'customer_id' => $customer->id,
                'ordered_by_id' => $orderedBy->id,
                'order_ref' => $this->generateOrderRef(),
                'quantity' => $data['quantity'],
                'unit_price' => $unitPrice,
                'seal_cost' => $sealCost,
                'freight_amount' => $freightAmount,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'payment_type' => $data['payment_type'],
                'billing_location_id' => $data['billing_location_id'],
                'shipping_location_id' => $data['shipping_location_id'],
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_contact' => $data['receiver_contact'] ?? null,
                'sepio_order_ports' => $ports->pluck('code')->all(),
                'status' => SealOrderStatus::IlPending,
            ]);

            // Debit advance balance immediately if payment_type is advance_balance
            if ($data['payment_type'] === 'advance_balance') {
                $this->walletService->debit($wallet, $totalAmount, 'order', $order->id);
            }

            return $order;
        });
    }

    public function approve(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            $order->status !== SealOrderStatus::IlPending && $order->status !== SealOrderStatus::IlParked,
            422, 'Only pending or parked orders can be approved.'
        );

        $remarksFilePath = null;
        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("orders/{$order->id}/remarks");
        }

        $order->update([
            'status' => SealOrderStatus::IlApproved,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
            'il_remark_file_url' => $remarksFilePath,
        ]);

        return $order->fresh();
    }

    public function reject(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            $order->status !== SealOrderStatus::IlPending && $order->status !== SealOrderStatus::IlParked,
            422, 'Only pending or parked orders can be rejected.'
        );

        $remarksFilePath = null;
        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("orders/{$order->id}/remarks");
        }

        // Refund advance balance if it was debited
        if ($order->payment_type === 'advance_balance') {
            $wallet = $order->customer->wallet;
            $this->walletService->topUp($wallet, $order->total_amount, $by, 'Refund for rejected order ' . $order->order_ref);
        }

        $order->update([
            'status' => SealOrderStatus::IlRejected,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'],
            'il_remark_file_url' => $remarksFilePath,
        ]);

        return $order->fresh();
    }

    public function park(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            $order->status !== SealOrderStatus::IlPending,
            422, 'Only pending orders can be parked.'
        );

        $order->update([
            'status' => SealOrderStatus::IlParked,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        return $order->fresh();
    }

    private function assertPaymentViable(mixed $wallet, string $paymentType, int $quantity, float $unitPrice): void
    {
        if ($paymentType === 'advance_balance') {
            $total = round($quantity * $unitPrice * 1.18, 2);
            abort_if(
                !$wallet->hasSufficientBalance($total),
                422, "Insufficient advance balance. Required: ₹{$total}, Available: ₹{$wallet->cost_balance}."
            );
        }

        if ($paymentType === 'credit') {
            abort_if(
                $wallet->costing_type !== WalletCoastingType::Credit,
                422, 'Credit payment is not enabled for this account.'
            );

            $total = round($quantity * $unitPrice * 1.18, 2);
            abort_if(
                !$wallet->withinCreditLimit($total),
                422, "Credit limit exceeded. Available credit: ₹" . ($wallet->credit_capping - $wallet->credit_used) . "."
            );
        }
    }

    private function generateOrderRef(): string
    {
        $last = SealOrder::lockForUpdate()->latest('id')->value('order_ref');
        $next = $last ? (int)substr($last, 2) + 1 : 1;
        return 'IL' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
