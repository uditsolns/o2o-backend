<?php
// app/Http/Requests/Trip/PushLocationRequest.php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class PushLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth is handled in controller (token or sanctum)
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'heading' => ['nullable', 'integer', 'min:0', 'max:359'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
