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
            'il_policy_number' => $this->il_policy_number,
            'il_policy_expiry' => $this->il_policy_expiry,
            'sum_insured' => $this->sum_insured,
            'gwp' => $this->gwp,
            'costing_type' => $this->costing_type,
            'credit_period' => $this->credit_period,
            'credit_capping' => $this->credit_capping,
            'credit_used' => $this->credit_used,
            'freight_rate_per_seal' => $this->freight_rate_per_seal,
            'cost_balance' => $this->cost_balance,
            'pricing_tiers' => $this->whenLoaded('pricingTiers',
                fn() => $this->pricingTiers->map(fn($t) => [
                    'id' => $t->id,
                    'min_quantity' => $t->min_quantity,
                    'max_quantity' => $t->max_quantity,
                    'price_per_seal' => $t->price_per_seal,
                    'is_active' => $t->is_active,
                ])
            ),
            'updated_at' => $this->updated_at,
        ];
    }
}
