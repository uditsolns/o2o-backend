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
            'role' => [
                'name' => $this->role->name,
                'permissions' => $this->role->permissions->pluck('name'),
            ],
            'customer_id' => $this->customer_id,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
