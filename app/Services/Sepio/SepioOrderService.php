<?php

namespace App\Services\Sepio;

use App\Enums\SealOrderStatus;
use App\Models\SealOrder;
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
            ->whereIn('id', json_decode($order->sepio_order_ports ?? '[]', true) ?: [])
            ->get()
            ->map(fn($p) => "{$p->name} ({$p->code})")
            ->values()
            ->all();

        // If sepio_order_ports stored as code strings already, use those directly
        if (empty($orderPorts)) {
            $orderPorts = collect($order->sepio_order_ports ?? [])->all();
        }

        $wallet = $customer->wallet;

        $response = $this->client->postAs($customer, '/companyadmin/placedorder', [
            'sealType' => 'bolt',
            'companyId' => $customer->sepio_company_id,
            'shippingAddressId' => $shippingLocation->sepio_shipping_address_id,
            'billingAddressId' => $billingLocation->sepio_billing_address_id,
            'createdBy' => $customer->primary_contact_email ?? $customer->email,
//            'orderType' => $wallet->costing_type->value === 'credit' ? 'credit' : 'advance',
            'orderType' => 'credit',
            'sealCount' => $order->quantity,
            'orderPorts' => $orderPorts,
            'unitprice' => (float)$order->unit_price,
            'totalprice' => (float)$order->seal_cost,
            'freight' => (float)$order->freight_amount,
            'tax' => (float)$order->gst_amount,
            'grandtotal' => (float)$order->total_amount,
            'creditPeriod' => $wallet->credit_period,
            'distributorId' => config('sepio.distributor_id'),
            'deliveryId' => '1',
            'discrate' => 0,
            'purchaseOrderNumber' => null,
            'isSezUser' => 0,
            'sepioURL' => 'sepio/orders',
            'reqId' => $order->order_ref,
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
        ]);

        if ($response->failed() || empty($response->json('orderId'))) {
            Log::error('Sepio placeOrder failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);
            throw new \RuntimeException(
                'Sepio place order failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        $sepioOrderId = $response->json('orderId');

        $order->update([
            'sepio_order_id' => $sepioOrderId,
            'sepio_billing_address_id' => $billingLocation->sepio_billing_address_id,
            'sepio_shipping_address_id' => $shippingLocation->sepio_shipping_address_id,
            'status' => SealOrderStatus::MfgPending,
        ]);

        Log::info('Sepio order placed', [
            'order_id' => $order->id,
            'sepio_order_id' => $sepioOrderId,
        ]);
    }
}
