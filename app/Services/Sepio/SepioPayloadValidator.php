<?php

namespace App\Services\Sepio;

use Illuminate\Support\Facades\Validator;

class SepioPayloadValidator
{
    public static function registerCompany(array $payload): void
    {
        Validator::make($payload, [
            'companydetailsInfo.companyName' => ['required', 'string', 'max:100'],
            'companydetailsInfo.IEC' => ['required', 'alpha_num', 'size:10'],
            'companydetailsInfo.port' => ['required', 'string'],
            'companydetailsInfo.icd' => ['required', 'string'],
            'companydetailsInfo.sealRequest' => ['required'],
            'primaryContactInfo.fName' => ['required', 'string', 'max:50'],
            'primaryContactInfo.lName' => ['required', 'string', 'max:50'],
            'primaryContactInfo.email' => ['required', 'email'],
            'primaryContactInfo.contactNo' => ['required', 'digits:10'],
            'primaryContactInfo.password' => ['required', 'string', 'max:20'],
            'primaryContactInfo.conpassword' => ['required', 'same:primaryContactInfo.password'],
            'register_from_type' => ['nullable', 'in:ILGIC'],
        ])->validate();
    }

    public static function updateAddress(array $payload): void
    {
        Validator::make($payload, [
            'createdBy' => ['required', 'email'],
            'companyId' => ['required'],
            'billingAddressInfo.billAddresses' => ['required', 'array', 'min:1'],
            'billingAddressInfo.billAddresses.*.billingCompanyName' => ['required', 'string'],
            'billingAddressInfo.billAddresses.*.address' => ['required', 'string'],
            'billingAddressInfo.billAddresses.*.city' => ['required', 'string'],
            'billingAddressInfo.billAddresses.*.state' => ['required', 'string'],
            'billingAddressInfo.billAddresses.*.zipcode' => ['required', 'digits:6'],
            'billingAddressInfo.billAddresses.*.gstno' => ['required', 'string', 'size:15'],
            'shippingAddressInfo.addresses' => ['required', 'array', 'min:1'],
            'shippingAddressInfo.addresses.*.address' => ['required', 'string'],
            'shippingAddressInfo.addresses.*.city' => ['required', 'string'],
            'shippingAddressInfo.addresses.*.state' => ['required', 'string'],
            'shippingAddressInfo.addresses.*.zipcode' => ['required', 'digits:6'],
            'fclFlag' => ['required', 'in:0,1'],
            'cfsFlag' => ['required', 'in:0,1'],
            'warehouseFlag' => ['required', 'in:0,1'],
        ])->validate();
    }

    public static function uploadKyc(array $payload): void
    {
        $rules = [
            'companyId' => ['required'],
            'dateNow' => ['required', 'integer'],
            'documentExtension' => ['required', 'string'],
            'documentName' => ['required', 'string'],
        ];

        if (array_key_exists('documentType', $payload)) {
            $rules += [
                'IEC' => ['required', 'string'],
                'documentType' => ['required', 'string'],
                'fclFlag' => ['required', 'in:0,1'],
                'cfsFlag' => ['required', 'in:0,1'],
                'warehouseFlag' => ['required', 'in:0,1'],
            ];
        }

        Validator::make($payload, $rules)->validate();
    }

    public static function updateCompanyDetails(array $payload): void
    {
        Validator::make($payload, [
            'companyAddressInfo.companyName' => ['required', 'string'],
            'companyAddressInfo.fclFlag' => ['required', 'in:0,1'],
            'companyAddressInfo.cfsFlag' => ['required', 'in:0,1'],
            'companyAddressInfo.warehouseFlag' => ['required', 'in:0,1'],
            'billingAddressInfo' => ['required', 'array'],
            'billingAddressInfo.*.addressId' => ['required'],
            'shippingAddressInfo' => ['required', 'array'],
            'shippingAddressInfo.*.addressId' => ['required'],
        ])->validate();
    }

    public static function placeOrder(array $payload): void
    {
        Validator::make($payload, [
            'companyId' => ['required', 'regex:/^\d+$/'],
            'createdBy' => ['required', 'email'],
            'orderType' => ['required', 'in:credit,advance'],
            'sealType' => ['required', 'in:bolt,cfs'],
            'creditPeriod' => ['required_if:orderType,credit', 'integer', 'min:1'],
            'distributorId' => ['required_if:deliveryId,2'],
            'sealCount' => ['required', 'integer', 'min:1'],
            'orderPorts' => ['required', 'array', 'min:1'],
            'unitprice' => ['required', 'numeric', 'gt:0'],
            'totalprice' => ['required', 'numeric', 'gt:0'],
            'freight' => ['required', 'numeric', 'gt:0'],
            'tax' => ['required', 'numeric', 'gt:0'],
            'grandtotal' => ['required', 'numeric', 'gt:0'],
            'discrate' => ['nullable', 'numeric', 'min:0'],
            'totalRoundOff' => ['nullable', 'numeric', 'min:0'],
            'isSezUser' => ['nullable', 'in:0,1'],
            'shippingAddressId' => ['required', 'regex:/^address\d{8}$/'],
            'billingAddressId' => ['required', 'regex:/^address\d{8}$/'],
            'deliveryId' => ['required', 'in:1,2'],
            'sepioURL' => ['required', 'string'],
            'shippingInfo' => ['required', 'array'],
            'billingInfo' => ['required', 'array'],
            'billingInfo.gstno' => ['required', 'string', 'size:15'],
        ])->validate();
    }

    public static function installSeal(array $payload): void
    {
        Validator::make($payload, [
            'sealString' => ['required', 'string'],
            'sealNo' => ['required', 'string'],
            'companyId' => ['required'],
            'createdBy' => ['required', 'email'],
            'shippingBillNo' => ['required', 'array', 'min:1'],
            'shippingBillDate' => [
                'required',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($payload) {
                    if (count($value) !== count($payload['shippingBillNo'] ?? [])) {
                        $fail('shippingBillDate must have the same number of entries as shippingBillNo.');
                    }
                },
            ],
            'shippingBillDate.*' => ['date_format:d-m-Y'],
            'destinationStation' => ['required', 'string'],
            'sealingDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:' . now()->subDays(30)->format('Y-m-d'),
                'before_or_equal:' . now()->addDays(30)->format('Y-m-d'),
            ],
            'sealingTime' => ['required', 'date_format:H:i:s'],
            'containerNo' => ['required', 'string'],
            'truckNo' => ['required', 'string'],
            'orderId' => ['required', 'string'],
        ])->validate();
    }
}
