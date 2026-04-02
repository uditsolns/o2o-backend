<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['nullable', 'string'],
            'trip_type' => ['nullable', 'string'],
            'transport_mode' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
            'sepio_status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
