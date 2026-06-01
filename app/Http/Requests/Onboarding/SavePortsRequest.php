<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\PortCategory;
use App\Models\Customer;
use App\Models\Port;
use Illuminate\Foundation\Http\FormRequest;

class SavePortsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'port_ids' => ['required', 'array', 'min:1'],
            'port_ids.*' => ['integer', 'exists:ports,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            // Only validate ICD+port requirement for Sepio customers
            $customer = $user->isPlatformUser()
                ? Customer::find($this->input('customer_id'))
                : $user->customer;

            if (!$customer || !$customer->isSepioEnabled()) {
                return;
            }

            $selectedPorts = Port::whereIn('id', $this->input('port_ids', []))
                ->where('is_active', true)
                ->get();

            $hasPort = $selectedPorts->contains(fn($p) => $p->port_category === PortCategory::Port);

            $hasIcd = $selectedPorts->contains(fn($p) => $p->port_category === PortCategory::Icd);

            if (!$hasPort) {
                $validator->errors()->add('port_ids', 'At least one Port (port category) must be selected for Sepio customers.');
            }

            if (!$hasIcd) {
                $validator->errors()->add('port_ids', 'At least one ICD port must be selected for Sepio customers.');
            }
        });
    }
}
