<?php

namespace App\Services\Sepio;

use App\Models\Customer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class SepioClient
{
    public function __construct(private SepioAuthService $authService)
    {
    }

    // ── Unauthenticated ───────────────────────────────────────────────────────

    public function post(string $endpoint, array $payload): Response
    {
        return $this->send('post', $endpoint, $payload);
    }

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->send('get', $endpoint, $query);
    }

    // ── Authenticated (per-customer JWT) ──────────────────────────────────────

    public function postAs(Customer $customer, string $endpoint, array $payload): Response
    {
        return $this->sendAs($customer, 'post', $endpoint, $payload);
    }

    public function getAs(Customer $customer, string $endpoint, array $query = []): Response
    {
        return $this->sendAs($customer, 'get', $endpoint, $query);
    }

    public function postFileAs(
        Customer $customer,
        string   $endpoint,
        array    $payload,
        string   $fileContents,
        string   $fileName
    ): Response
    {
        $token = $this->authService->getToken($customer);

        $response = $this->buildFileRequest($token, $fileContents, $fileName)
            ->post(config('sepio.base_url') . $endpoint, $payload);

        if ($response->status() === 401) {
            $token = $this->authService->refreshToken($customer);
            $response = $this->buildFileRequest($token, $fileContents, $fileName)
                ->post(config('sepio.base_url') . $endpoint, $payload);
        }

        $this->logIfFailed($endpoint, $response);

        return $response;
    }

    private function buildFileRequest(string $token, string $fileContents, string $fileName): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($token)
            ->timeout(60)
            ->retry(2, 500)
            ->attach('file', $fileContents, $fileName);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function send(string $method, string $endpoint, array $data): Response
    {
        $http = Http::baseUrl(config('sepio.base_url'))
            ->accept('application/json')
            ->timeout(30)
            ->retry(2, 500);

        $response = $method === 'get'
            ? $http->get($endpoint, $data)
            : $http->post($endpoint, $data);

        $this->logIfFailed($endpoint, $response);

        return $response;
    }

    private function sendAs(Customer $customer, string $method, string $endpoint, array $data): Response
    {
        $token = $this->authService->getToken($customer);

        $http = Http::baseUrl(config('sepio.base_url'))
            ->accept('application/json')
            ->withToken($token)
            ->timeout(30)
            ->retry(2, 500);

        $response = $method === 'get'
            ? $http->get($endpoint, $data)
            : $http->post($endpoint, $data);

        // Token expired mid-request — refresh once and retry
        if ($response->status() === 401) {
            $token = $this->authService->refreshToken($customer);
            $response = $method === 'get'
                ? $http->withToken($token)->get($endpoint, $data)
                : $http->withToken($token)->asMultipart()->post($endpoint, $data);
        }

        $this->logIfFailed($endpoint, $response);

        return $response;
    }

    private function logIfFailed(string $endpoint, Response $response): void
    {
        if ($response->failed()) {
            Log::warning('Sepio API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }
    }
}
