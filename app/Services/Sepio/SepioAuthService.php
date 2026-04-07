<?php

namespace App\Services\Sepio;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class SepioAuthService
{
    public function __construct(private SepioEncryption $encryption)
    {
    }

    /**
     * Returns a valid JWT for the given customer.
     * Refreshes if expired or missing.
     */
    public function getToken(Customer $customer): string
    {
        if (
            $customer->sepio_token &&
            $customer->sepio_token_expires_at &&
            $customer->sepio_token_expires_at->gt(now()->addMinutes(10))
        ) {
            return $customer->sepio_token;
        }

        return $this->refreshToken($customer);
    }

    public function refreshToken(Customer $customer): string
    {
        $credentials = $customer->sepio_credentials;

        abort_if(
            !$credentials,
            500,
            "Sepio credentials not found for customer #{$customer->id}"
        );

        $response = Http::baseUrl(config('sepio.base_url'))
            ->post('/users/login', [
                'username' => $this->encryption->encrypt($credentials['email']),
                'password' => $this->encryption->encrypt($credentials['password']),
            ]);

        if ($response->failed() || empty($response->json('token'))) {
            Log::error('Sepio login failed', [
                'customer_id' => $customer->id,
                'response' => $response->json(),
            ]);
            abort(502, 'Sepio authentication failed.');
        }

        $token = $response->json('token');

        $customer->update([
            'sepio_token' => $token,
            'sepio_token_expires_at' => now()->addHours(8),
        ]);

        return $token;
    }
}
