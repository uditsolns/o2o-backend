<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'company_type' => $this->company_type,
            'industry_type' => $this->industry_type,
            'onboarding_status' => $this->onboarding_status,
            'is_active' => $this->is_active,
            'iec_number' => $this->iec_number,
            'gst_number' => $this->gst_number,
            'pan_number' => $this->pan_number,
            'cin_number' => $this->cin_number,
            'tin_number' => $this->tin_number,
            'cha_number' => $this->cha_number,
            'billing_address' => $this->billing_address,
            'billing_landmark' => $this->billing_landmark,
            'billing_city' => $this->billing_city,
            'billing_state' => $this->billing_state,
            'billing_pincode' => $this->billing_pincode,
            'billing_country' => $this->billing_country,
            'primary_contact_name' => $this->primary_contact_name,
            'primary_contact_email' => $this->primary_contact_email,
            'primary_contact_mobile' => $this->primary_contact_mobile,
            'alternate_contact_name' => $this->alternate_contact_name,
            'alternate_contact_phone' => $this->alternate_contact_phone,
            'alternate_contact_email' => $this->alternate_contact_email,
            'il_remarks' => $this->il_remarks,
            'il_approved_at' => $this->il_approved_at,
            'approved_by' => $this->whenLoaded('approvedBy', fn() => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),
            'wallet' => $this->whenLoaded('wallet', fn() => [
                'costing_type' => $this->wallet->costing_type,
                'cost_balance' => $this->wallet->cost_balance,
                'credit_used' => $this->wallet->credit_used,
                'credit_capping' => $this->wallet->credit_capping,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
