<?php

namespace App\Http\Resources;

use App\Models\CustomerWallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerWallet */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'costing_type' => $this->costing_type,
            'credit_period' => $this->credit_period,
            'credit_capping' => $this->credit_capping,
            'credit_used' => $this->credit_used,
            'available_credit' => $this->credit_capping
                ? max(0, $this->credit_capping - $this->credit_used)
                : null,
            'freight_rate_per_seal' => $this->freight_rate_per_seal,
            'cost_balance' => $this->cost_balance,
            'low_balance_threshold' => $this->low_balance_threshold,
            'pricing_tiers' => $this->whenLoaded('pricingTiers',
                fn() => $this->pricingTiers->map(fn($t) => [
                    'id' => $t->id,
                    'min_quantity' => $t->min_quantity,
                    'max_quantity' => $t->max_quantity,
                    'price_per_seal' => $t->price_per_seal,
                    'is_active' => $t->is_active,
                ])
            ),
            'trip_pricing_rules' => $this->whenLoaded('tripPricingRules',
                fn() => $this->tripPricingRules->map(fn($r) => [
                    'id' => $r->id,
                    'trip_type' => $r->trip_type,
                    'transport_mode' => $r->transport_mode,
                    'price_per_trip' => $r->price_per_trip,
                    'is_active' => $r->is_active,
                ])
            ),
            'updated_at' => $this->updated_at,
        ];
    }
}
