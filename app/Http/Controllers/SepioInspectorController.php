<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Sepio\SepioAuthService;
use App\Services\Sepio\SepioClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SepioInspectorController extends Controller
{
    private function guardProd(): void
    {
        abort_if(app()->isProduction(), 404);
    }

    public function index(): View
    {
        $this->guardProd();
        return view('sepio.inspector');
    }

    public function me(Request $request): JsonResponse
    {
        $this->guardProd();
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function customers(): JsonResponse
    {
        $this->guardProd();

        $customers = Customer::select([
            'id', 'company_name', 'email', 'first_name', 'last_name',
            'sepio_company_id', 'primary_contact_email',
            'iec_number', 'gst_number', 'onboarding_status',
        ])
            ->orderBy('company_name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'company_name' => $c->company_name,
                'email' => $c->email,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'primary_contact_email' => $c->primary_contact_email,
                'sepio_company_id' => $c->sepio_company_id,
                'iec_number' => $c->iec_number,
                'gst_number' => $c->gst_number,
                'onboarding_status' => $c->onboarding_status instanceof \UnitEnum
                    ? $c->onboarding_status->value
                    : $c->onboarding_status,
            ]);

        return response()->json($customers);
    }

    public function proxy(Request $request, SepioClient $client): JsonResponse
    {
        $this->guardProd();

        $endpoint = $request->input('endpoint');
        $method = strtolower($request->input('method', 'post'));
        $customerId = $request->input('customer_id');
        $authenticated = $request->boolean('authenticated', false);
        $payloadRaw = $request->input('payload', '{}');

        $payload = is_array($payloadRaw) ? $payloadRaw : (json_decode($payloadRaw, true) ?? []);
        $customer = $customerId ? Customer::find($customerId) : null;
        $start = microtime(true);

        try {
            if ($authenticated && $customer) {
                $response = $method === 'get'
                    ? $client->getAs($customer, $endpoint, $payload)
                    : $client->postAs($customer, $endpoint, $payload);
            } else {
                $response = $method === 'get'
                    ? $client->get($endpoint, $payload)
                    : $client->post($endpoint, $payload);
            }

            return response()->json([
                'status' => $response->status(),
                'body' => $response->json() ?? ['_raw' => $response->body()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        }
    }

    public function proxyFile(Request $request, SepioClient $client): JsonResponse
    {
        $this->guardProd();

        $endpoint = $request->input('endpoint');
        $customerId = $request->input('customer_id');
        $payload = json_decode($request->input('payload', '{}'), true) ?? [];
        $customer = Customer::findOrFail($customerId);
        $file = $request->file('file');
        $start = microtime(true);

        try {
            $response = $client->postFileAs(
                $customer,
                $endpoint,
                $payload,
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            );

            return response()->json([
                'status' => $response->status(),
                'body' => $response->json() ?? ['_raw' => $response->body()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        }
    }

    public function refreshToken(Request $request, SepioAuthService $authService): JsonResponse
    {
        $this->guardProd();

        $customer = Customer::findOrFail($request->input('customer_id'));
        $start = microtime(true);

        try {
            $token = $authService->refreshToken($customer);
            $customer->refresh();

            return response()->json([
                'status' => 200,
                'body' => [
                    'message' => 'Token refreshed successfully.',
                    'token' => substr($token, 0, 40) . '…',
                    'expires_at' => $customer->sepio_token_expires_at?->toISOString(),
                ],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        }
    }
}
