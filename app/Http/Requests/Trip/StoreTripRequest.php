<?php

namespace App\Http\Requests\Trip;

use App\Enums\{TripTransportationMode, TripType};
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = $this->input('transport_mode');
        $isRoad = in_array($mode, ['road', 'multimodal']);
        $isSea = in_array($mode, ['sea', 'multimodal']);
        $usesSeals = $this->boolean('uses_sepio_seal');

        // Sepio customers carry stricter cargo/shipping-bill requirements;
        // non-Sepio customers can omit them entirely.
        $isSepioCustomer = $this->resolveIsSepioCustomer();

        return [
            'customer_id' => ['nullable', Rule::requiredIf($this->user()->isPlatformUser()), 'integer', 'exists:customers,id'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'transport_mode' => ['required', Rule::enum(TripTransportationMode::class)],

            // Seal — only required when user opts into Sepio seals
            'uses_sepio_seal' => ['required', 'boolean'],
            'seal_id' => [
                Rule::requiredIf($usesSeals),
                'nullable',
                'integer',
                'exists:seals,id',
            ],

            // Driver — required for road / multimodal
            'driver_name' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:255'],
            'driver_phone' => [Rule::requiredIf($isRoad), 'nullable', 'string', 'max:20'],
            'driver_license' => ['nullable', 'string', 'max:50'],
            'driver_aadhaar' => ['nullable', 'string', 'max:20'],
            'is_driver_license_verified' => ['sometimes', 'boolean'],
            'is_driver_aadhaar_verified' => ['sometimes', 'boolean'],
            'driver_license_verification_payload' => ['nullable', 'json'],
            'driver_aadhaar_verification_payload' => ['nullable', 'json'],

            // Vehicle — required for road / multimodal
            'vehicle_number' => [
                Rule::requiredIf($isRoad),
                'nullable',
                'string',
                // Indian vehicle reg (5-11 chars) OR VIN/chassis (17-20 chars), uppercase alphanumeric
                'regex:/^[A-Z0-9]{5,11}$|^[A-Z0-9]{17,20}$/',
                'max:20',
            ],
            'vehicle_type' => [Rule::requiredIf($isRoad), 'nullable', Rule::in(['truck', 'trailer', 'container_carrier'])],
            'is_rc_verified' => ['sometimes', 'boolean'],
            'is_verification_done' => ['sometimes', 'boolean'],
            'rc_verification_payload' => ['nullable', 'json'],

            // Container — required for sea / multimodal
            'container_number' => [Rule::requiredIf($isSea), 'nullable', 'string', 'max:50'],
            'container_type' => ['nullable', 'string', 'max:20'],

            // Carrier SCAC — required for sea/multimodal so Kpler tracking can be registered
            'carrier_scac' => [
                Rule::requiredIf($isSea),
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]{2,10}$/',
            ],

            // Cargo — optional for all customers. The trip can be created
            // without any cargo metadata and these fields can be filled in
            // later via the update endpoint.
            'cargo_type' => ['nullable', 'string', 'max:100'],
            'cargo_description' => ['nullable', 'string'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'eway_bill_number' => ['nullable', 'string', 'regex:/^\d{7,15}$/', 'max:20'],
            'eway_bill_validity_date' => ['nullable', 'date'],

            // Shipping bill — the only Sepio-gated cargo field. Sepio seal
            // installation requires the bill ref, so it is required when the
            // customer is on Sepio OR when this specific trip opts into a
            // Sepio seal. Non-Sepio customers can omit it entirely.
            'shipping_bill_no' => [
                Rule::requiredIf($isSepioCustomer || $usesSeals),
                'nullable',
                'string',
                'regex:/^\d{7}$/',
                'max:20',
            ],
            'shipping_bill_date' => [
                Rule::requiredIf($isSepioCustomer || $usesSeals),
                'nullable',
                'date',
            ],

            // Cargo extras — optional for everyone
            'hs_code' => ['nullable', 'string', 'max:20'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'net_weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_unit' => ['nullable', 'string', 'max:50'],
            'declared_cargo_value' => ['nullable', 'numeric', 'min:0'],

            // Dispatch location — required for road / multimodal
            'dispatch_location_name' => ['nullable', 'string', 'max:255'],
            'dispatch_address' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string'],
            'dispatch_city' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'dispatch_state' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'dispatch_pincode' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:10'],
            'dispatch_country' => ['nullable', Rule::requiredIf($isRoad), 'string', 'max:100'],
            'dispatch_contact_person' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:255'],
            'dispatch_contact_number' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:20'],
            'dispatch_contact_email' => ['nullable', Rule::requiredIf($isRoad), 'email'],
            'dispatch_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'dispatch_lng' => ['nullable', 'numeric', 'between:-180,180'],

            // Delivery location — required for road / multimodal
            'delivery_location_name' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string'],
            'delivery_city' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'delivery_state' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:100'],
            'delivery_pincode' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:10'],
            'delivery_country' => ['nullable', Rule::requiredIf($isRoad), 'string', 'max:100'],
            'delivery_contact_person' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:255'],
            'delivery_contact_number' => ['nullable', Rule::requiredIf($isRoad), 'nullable', 'string', 'max:20'],
            'delivery_contact_email' => ['nullable', Rule::requiredIf($isRoad), 'email'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],

            // Origin port — NOW OPTIONAL even for sea/multimodal (Kpler will fill)
            'origin_port_name' => ['nullable', 'string', 'max:255'],
            'origin_port_code' => ['nullable', 'string', 'max:20'],
            'origin_port_category' => ['nullable', 'string', 'max:20'],

            // Destination port — NOW OPTIONAL even for sea/multimodal (Kpler will fill)
            'destination_port_name' => ['nullable', 'string', 'max:255'],
            'destination_port_code' => ['nullable', 'string', 'max:20'],
            'destination_port_category' => ['nullable', 'string', 'max:20'],

            // Timeline — both optional now; dispatch_date defaults to today() in service
            'dispatch_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:dispatch_date'],

            // Vessel — optional at creation
            'vessel_name' => ['nullable', 'string', 'max:255'],
            'vessel_imo_number' => ['nullable', 'string', 'max:20'],
            'voyage_number' => ['nullable', 'string', 'max:100'],
            'bill_of_lading' => ['nullable', 'string', 'max:100'],
            'eta' => ['nullable', 'date'],
            'etd' => ['nullable', 'date'],

            // Segments
            'segments' => ['nullable', 'array'],
            'segments.*.sequence' => ['required', 'integer', 'min:1'],
            'segments.*.source_name' => ['required', 'string', 'max:255'],
            'segments.*.destination_name' => ['required', 'string', 'max:255'],
            'segments.*.transport_mode' => ['required', Rule::in(['road', 'sea'])],
            'segments.*.tracking_source' => ['nullable', Rule::in(['gps', 'tcl_tracker', 'e_lock', 'driver_mobile', 'driver_sim', 'fast_tag'])],
            'segments.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'eway_bill_number.regex' => 'Shipping bill number must be numeric digits only (7–15 digits).',
            'shipping_bill_no.regex' => 'Shipping bill number must be exactly 7 digits.',
            'seal_id.required_if' => 'A seal must be selected when using Sepio seals.',
            'shipping_bill_no.required' => 'Shipping bill number is required.',
            'shipping_bill_date.required' => 'Shipping bill date is required.',
            'vehicle_number.regex' => 'Vehicle number must be 5–11 or 17–20 uppercase alphanumeric characters (e.g. MH01AB1234).',
        ];
    }

    /**
     * Look up the target customer and check whether Sepio is enabled.
     * Client users always operate against their own customer; platform users
     * pass customer_id explicitly in the payload.
     */
    private function resolveIsSepioCustomer(): bool
    {
        $user = $this->user();

        $customerId = $user->isClientUser()
            ? $user->customer_id
            : $this->input('customer_id');

        if (!$customerId) {
            return false;
        }

        return (bool) Customer::whereKey($customerId)->value('sepio_enabled');
    }
}
