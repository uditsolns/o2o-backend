<?php

namespace App\Services\Sepio;

use App\Enums\SealOrderStatus;
use App\Exceptions\SepioException;
use App\Models\SealOrder;
use App\Models\SealOrderHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class SepioOrderService
{
    public function __construct(private SepioClient $client)
    {
    }

    public function placeOrder(SealOrder $order): void
    {
        $customer = $order->customer;

        $billingLocation = $order->billingLocation;
        $shippingLocation = $order->shippingLocation;

        abort_if(
            !$billingLocation?->sepio_billing_address_id,
            422,
            'Billing location is not synced with Sepio yet (missing billing address ID). Please try again in a moment.'
        );

        abort_if(
            !$shippingLocation?->sepio_shipping_address_id,
            422,
            'Shipping location is not synced with Sepio yet (missing shipping address ID). Please try again in a moment.'
        );

        // Resolve port strings from customer_ports
        $orderPorts = $customer->ports()
            ->whereIn('code', $order->sepio_order_ports)
            ->get()
            ->map(fn($p) => "{$p->name} ({$p->code})")
            ->values()
            ->all();

        // If sepio_order_ports stored as code strings already, use those directly
        if (empty($orderPorts)) {
            $orderPorts = collect($order->sepio_order_ports ?? [])->all();
        }

        $payload = [
            'sealType' => 'bolt',
            'companyId' => $customer->sepio_company_id,
            'shippingAddressId' => $shippingLocation->sepio_shipping_address_id,
            'billingAddressId' => $billingLocation->sepio_billing_address_id,
            'createdBy' => $customer->primary_contact_email ?? $customer->email,
            'orderType' => 'credit',
            'sealCount' => $order->quantity,
            'orderPorts' => $orderPorts,
            'unitprice' => 1,
            'totalprice' => 1,
            'freight' => 1,
            'tax' => 1,
            'grandtotal' => 1,
            'creditPeriod' => 30,
            'distributorId' => config('sepio.distributor_id'),
            'deliveryId' => '1',
            'discrate' => 0,
            'purchaseOrderNumber' => null,
            'isSezUser' => 0,
            'sepioURL' => 'sepio/orders',
            'totalRoundOff' => 0,
            'shippingInfo' => [
                'address' => $shippingLocation->address,
                'city' => $shippingLocation->city,
                'landmark' => $shippingLocation->landmark ?? '',
                'state' => strtoupper($shippingLocation->state),
                'zip' => $shippingLocation->pincode,
            ],
            'billingInfo' => [
                'billingCompanyName' => $customer->company_name,
                'gstno' => $billingLocation->gst_number ?? $customer->gst_number,
                'address' => $billingLocation->address,
                'city' => $billingLocation->city,
                'landmark' => $billingLocation->landmark ?? '',
                'state' => strtoupper($billingLocation->state),
                'zip' => $billingLocation->pincode,
            ],
        ];

        SepioPayloadValidator::placeOrder($payload);

        $response = $this->client->postAs($customer, '/companyadmin/placedorder', $payload);

        if ($response->failed() || empty($response->json('orderId'))) {
            $json = $response->json() ?? [];
            $msg = SepioException::extractMessage($json) ?: 'Sepio place order failed.';
            Log::error('Sepio placeOrder failed', ['order_id' => $order->id, 'error' => $msg]);
            throw SepioException::fromResponse($json, $msg);
        }

        $sepioOrderId = $response->json('orderId');

        // Wrap status flip + history insert in a transaction so the lifecycle
        // ledger stays in lockstep with the order row.
        DB::transaction(function () use ($order, $sepioOrderId, $billingLocation, $shippingLocation) {
            $fromStatus = $order->status->value;

            $order->update([
                'sepio_order_id' => $sepioOrderId,
                'sepio_billing_address_id' => $billingLocation->sepio_billing_address_id,
                'sepio_shipping_address_id' => $shippingLocation->sepio_shipping_address_id,
                'status' => SealOrderStatus::MfgPending,
            ]);

            SealOrderHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => SealOrderStatus::MfgPending->value,
                'actor_type' => 'system',
                'actor_id' => null,
                'remarks' => "Order placed with Sepio (ref: {$sepioOrderId}).",
            ]);
        });

        Log::info('Sepio order placed', [
            'order_id' => $order->id,
            'sepio_order_id' => $sepioOrderId,
        ]);
    }
}
