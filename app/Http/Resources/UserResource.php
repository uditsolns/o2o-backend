<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'status' => $this->status,
            'role' => $this->whenLoaded('role', fn() => [
                'name' => $this->role->name,
                'permissions' => $this->when($this->role->relationLoaded('permissions'),
                    $this->role->permissions->pluck('name'))
            ]),
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'email' => $this->customer->email,
                'mobile' => $this->customer->mobile,
                'company_name' => $this->customer->company_name,
                'onboarding_status' => $this->customer->onboarding_status,
            ]),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
