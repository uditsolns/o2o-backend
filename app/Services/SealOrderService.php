<?php

namespace App\Services;

use App\Enums\SealOrderStatus;
use App\Enums\WalletCoastingType;
use App\Jobs\SepioPlaceOrderJob;
use App\Models\Customer;
use App\Models\CustomerPort;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\SealOrder;
use App\Models\User;
use App\Services\Concerns\RecordsHistory;
use Illuminate\Support\Facades\DB;

class SealOrderService
{
    use RecordsHistory;

    public function __construct(private WalletService $walletService)
    {
    }

    public function store(array $data, User $orderedBy): SealOrder
    {
        if ($orderedBy->isPlatformUser()) {
            $customerId = $data['customer_id'] ?? null;
            abort_if(!$customerId, 400, 'customer_id is required for platform users.');
            $customer = Customer::findOrFail($customerId);
        } else {
            $customer = $orderedBy->customer;
        }

        abort_unless(
            $customer->isSepioEnabled() && $customer->isSepioVerified(),
            403,
            'Seal orders require Sepio integration to be enabled and verified.'
        );

        $wallet = $customer->wallet;
        abort_if(!$wallet, 422, 'Customer wallet has not been configured yet.');

        $unitPrice = $this->walletService->resolvePriceForQuantity($customer, $data['quantity']);
        abort_if(!$unitPrice, 422, 'No active pricing tier found for the requested quantity.');

        $sealCost = round($data['quantity'] * $unitPrice, 2);
        $freightAmount = round($data['quantity'] * $wallet->freight_rate_per_seal, 2);
        $gstAmount = round(($sealCost + $freightAmount) * 0.18, 2);
        $totalAmount = round($sealCost + $freightAmount + $gstAmount, 2);

        $this->assertPaymentViable($wallet, $data['payment_type'], $totalAmount);

        $ports = CustomerPort::whereIn('id', $data['port_ids'])
            ->where('customer_id', $customer->id)
            ->get();

        abort_if($ports->count() !== count($data['port_ids']), 422, 'One or more selected ports are invalid.');

        return DB::transaction(function () use ($data, $orderedBy, $customer, $wallet, $unitPrice, $sealCost, $freightAmount, $gstAmount, $totalAmount, $ports) {

            // Determine payment_status for cash orders
            $paymentStatus = match ($data['payment_type']) {
                'cash' => 'pending_payment',
                default => 'not_applicable',
            };

            $order = SealOrder::create([
                'customer_id' => $customer->id,
                'ordered_by_id' => $orderedBy->id,
                'parent_order_id' => $data['parent_order_id'] ?? null,
                'order_ref' => $this->generateOrderRef(),
                'quantity' => $data['quantity'],
                'unit_price' => $unitPrice,
                'seal_cost' => $sealCost,
                'freight_amount' => $freightAmount,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'payment_type' => $data['payment_type'],
                'payment_status' => $paymentStatus,
                'billing_location_id' => $data['billing_location_id'],
                'shipping_location_id' => $data['shipping_location_id'],
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_contact' => $data['receiver_contact'] ?? null,
                'sepio_order_ports' => $ports->pluck('code')->all(),
                'status' => SealOrderStatus::IlPending,
            ]);

            $this->recordOrderHistory(
                $order->id,
                null,
                SealOrderStatus::IlPending->value,
                'customer',
                $orderedBy->id,
                'Order placed.'
            );

            // Debit advance balance immediately
            if ($data['payment_type'] === 'advance_balance') {
                $this->walletService->debit(
                    $wallet,
                    $totalAmount,
                    'advance_debit',
                    $order->id
                );
            }

            // Draw credit immediately
            if ($data['payment_type'] === 'credit') {
                $this->walletService->drawCredit(
                    $wallet,
                    $totalAmount,
                    $order->id
                );
            }

            return $order;
        });
    }

    public function approve(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            !in_array($order->status, [SealOrderStatus::IlPending, SealOrderStatus::IlParked]),
            422,
            'Only pending or parked orders can be approved.'
        );

        $remarksFilePath = null;
        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("orders/{$order->id}/remarks");
        }

        $fromStatus = $order->status->value;

        $order->update([
            'status' => SealOrderStatus::IlApproved,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
            'il_remark_file_url' => $remarksFilePath,
        ]);

        $this->recordOrderHistory(
            $order->id,
            $fromStatus,
            SealOrderStatus::IlApproved->value,
            'platform',
            $by->id,
            $data['remarks'] ?? null,
            $remarksFilePath,
        );

        SepioPlaceOrderJob::dispatch($order->fresh());

        return $order->fresh();
    }

    public function reject(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            !in_array($order->status, [SealOrderStatus::IlPending, SealOrderStatus::IlParked]),
            422,
            'Only pending or parked orders can be rejected.'
        );

        $remarksFilePath = null;
        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("orders/{$order->id}/remarks");
        }

        $fromStatus = $order->status->value;

        // Refund based on payment type
        if ($order->payment_type === 'advance_balance') {
            $wallet = $order->customer->wallet;
            $this->walletService->credit(
                $wallet,
                $order->total_amount,
                'advance_refund',
                $order->id
            );
        }

        if ($order->payment_type === 'credit') {
            $wallet = $order->customer->wallet;
            $this->walletService->releaseCredit(
                $wallet,
                $order->total_amount,
                $order->id
            );
        }

        $order->update([
            'status' => SealOrderStatus::IlRejected,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'],
            'il_remark_file_url' => $remarksFilePath,
        ]);

        $this->recordOrderHistory(
            $order->id,
            $fromStatus,
            SealOrderStatus::IlRejected->value,
            'platform',
            $by->id,
            $data['remarks'],
            $remarksFilePath,
        );

        return $order->fresh();
    }

    public function park(SealOrder $order, array $data, User $by): SealOrder
    {
        abort_if(
            $order->status !== SealOrderStatus::IlPending,
            422,
            'Only pending orders can be parked.'
        );

        $remarksFilePath = null;
        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("orders/{$order->id}/remarks");
        }

        $order->update([
            'status' => SealOrderStatus::IlParked,
            'il_approved_by' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'],
            'il_remark_file_url' => $remarksFilePath,
        ]);

        $this->recordOrderHistory(
            $order->id,
            SealOrderStatus::IlPending->value,
            SealOrderStatus::IlParked->value,
            'platform',
            $by->id,
            $data['remarks'],
            $remarksFilePath,
        );

        return $order->fresh();
    }

    /**
     * Mark cash payment as received by platform.
     */
    public function markCashPaymentReceived(SealOrder $order, User $by, ?string $receiptFilePath = null): SealOrder
    {
        abort_if($order->payment_type !== 'cash', 422, 'Only cash orders require payment confirmation.');
        abort_if($order->payment_status === 'payment_received', 422, 'Payment already marked as received.');

        $order->update([
            'payment_status' => 'payment_received',
            'il_remark_file_url' => $receiptFilePath ?? $order->il_remark_file_url,
        ]);

        // Record in wallet ledger as informational entry (no balance change — cash is offline)
        $wallet = $order->customer->wallet;
        if ($wallet) {
            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $order->total_amount,
                'reference_type' => 'cash_payment_received',
                'reference_id' => $order->id,
                'balance_after' => $wallet->cost_balance, // unchanged
                'balance_type' => 'cost_balance',
                'receipt_file_url' => $receiptFilePath,
            ]);
        }

        $this->recordOrderHistory(
            $order->id,
            $order->status->value,
            $order->status->value,
            'platform',
            $by->id,
            'Cash payment confirmed as received.'
        );

        return $order->fresh();
    }

    private function assertPaymentViable(
        CustomerWallet $wallet,
        string         $paymentType,
        float          $totalAmount
    ): void
    {
        if ($paymentType === 'advance_balance') {
            abort_if(
                !$wallet->hasSufficientBalance($totalAmount),
                422,
                "Insufficient advance balance. Required: ₹{$totalAmount}, Available: ₹{$wallet->cost_balance}."
            );
        }

        if ($paymentType === 'credit') {
            abort_if(
                $wallet->costing_type !== WalletCoastingType::Credit,
                422,
                'Credit payment is not enabled for this account.'
            );
            $availableCredit = $wallet->credit_capping - $wallet->credit_used;
            abort_if(
                $totalAmount > $availableCredit,
                422,
                "Credit limit exceeded. Available credit: ₹{$availableCredit}."
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
