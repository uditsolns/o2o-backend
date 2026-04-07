<?php

namespace App\Jobs;

use App\Models\SealOrder;
use App\Services\Sepio\SepioOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SepioPlaceOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 60;

    public function __construct(private readonly SealOrder $order)
    {
    }

    public function handle(SepioOrderService $service): void
    {
        $service->placeOrder($this->order->fresh([
            'customer.wallet',
            'customer.ports',
            'billingLocation',
            'shippingLocation',
        ]));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SepioPlaceOrderJob failed permanently', [
            'order_id' => $this->order->id,
            'error' => $e->getMessage(),
        ]);
    }
}
