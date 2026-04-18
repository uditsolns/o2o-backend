<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPortController;
use App\Http\Controllers\CustomerWalletController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerLocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\CustomerRouteController;
use App\Http\Controllers\SealController;
use App\Http\Controllers\SealOrderController;
use App\Http\Controllers\SealPricingController;
use App\Http\Controllers\SepioInspectorController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripDocumentController;
use App\Http\Controllers\TripTrackingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });

        // Own profile
        Route::get('me', [UserController::class, 'me']);
        Route::patch('me', [UserController::class, 'updateMe']);
        Route::get('me/customer', [UserController::class, 'myCustomer']);

        // ── Users ─────────────────────────────────────────────────────────────
        // Platform admin → platform users | Client admin → own org users (TenantScope)
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);

        // ── Customers (IL only — CustomerPolicy gates client users) ───────────
        Route::apiResource('customers', CustomerController::class)->except('destroy');
        Route::patch('customers/{customer}/toggle-active', [CustomerController::class, 'toggleActive']);
        Route::post('customers/{customer}/approve', [CustomerController::class, 'approve']);
        Route::post('customers/{customer}/reject', [CustomerController::class, 'reject']);
        Route::post('customers/{customer}/park', [CustomerController::class, 'park']);
        Route::get('customers/{customer}/documents', [CustomerController::class, 'documents']);
        Route::get('customers/{customer}/seals', [CustomerController::class, 'seals']);
        Route::get('customers/{customer}/orders', [CustomerController::class, 'orders']);
        Route::get('customers/{customer}/trips', [CustomerController::class, 'trips']);

        // ── Ports (read: all auth; write: IL only via PortPolicy) ─────────────
        Route::apiResource('ports', PortController::class)->except('destroy');
        Route::patch('ports/{port}/toggle-active', [PortController::class, 'toggleActive']);

        // Wallet (IL manages; client reads)
        Route::get('customers/{customer}/wallet', [CustomerWalletController::class, 'show']);
        Route::post('customers/{customer}/wallet', [CustomerWalletController::class, 'store']);
        Route::put('customers/{customer}/wallet', [CustomerWalletController::class, 'update']);
        Route::post('customers/{customer}/wallet/top-up', [CustomerWalletController::class, 'topUp']);
        Route::get('customers/{customer}/wallet/transactions', [CustomerWalletController::class, 'transactions']);
        // Pricing tiers (IL manages; client reads own)
        Route::get('customers/{customer}/pricing', [SealPricingController::class, 'index']);
        Route::post('customers/{customer}/pricing', [SealPricingController::class, 'sync']);
        Route::get('pricing', [SealPricingController::class, 'index']);     // client reads own
        Route::post('pricing/calculate', [SealPricingController::class, 'calculate']); // client previews cost


        // ── Onboarding
        Route::prefix('onboarding')->group(function () {
            Route::get('status', [OnboardingController::class, 'status']);
            Route::post('profile', [OnboardingController::class, 'saveProfile']);
            Route::put('profile', [OnboardingController::class, 'saveProfile']);
            Route::post('signatories', [OnboardingController::class, 'addSignatory']);
            Route::put('signatories/{signatory}', [OnboardingController::class, 'updateSignatory']);
            Route::delete('signatories/{signatory}', [OnboardingController::class, 'deleteSignatory']);
            Route::post('documents', [OnboardingController::class, 'uploadDocument']);
            Route::delete('documents/{document}', [OnboardingController::class, 'deleteDocument']);
            Route::post('ports', [OnboardingController::class, 'savePorts']);
            Route::post('submit', [OnboardingController::class, 'submit']);
        });

        // ── Onboarding-gated routes ───────────────────────────────────────────
        Route::middleware('onboarded')->group(function () {

            // Locations — TenantScope auto-filters for client users
            Route::apiResource('locations', CustomerLocationController::class);
            Route::patch('locations/{location}/toggle-active', [CustomerLocationController::class, 'toggleActive']);

            // Routes (trip templates)
            Route::apiResource('routes', CustomerRouteController::class);
            Route::patch('routes/{route}/toggle-active', [CustomerRouteController::class, 'toggleActive']);

            // Customer ports (client manages own port selections)
            Route::apiResource('customer-ports', CustomerPortController::class)->except('destroy');
            Route::patch('customer-ports/{customerPort}/toggle-active', [CustomerPortController::class, 'toggleActive']);

            // Seal Orders
            Route::apiResource('orders', SealOrderController::class)->only(['index', 'store', 'show']);
            Route::post('orders/{order}/approve', [SealOrderController::class, 'approve']);
            Route::post('orders/{order}/reject', [SealOrderController::class, 'reject']);
            Route::post('orders/{order}/park', [SealOrderController::class, 'park']);
            // Seal ingestion — IL only, hangs off the order
            Route::post('orders/{order}/seals', [SealController::class, 'ingest']);

            // Seal Inventory
            Route::get('seals', [SealController::class, 'index']);
            Route::get('seals/{seal}', [SealController::class, 'show']);
            Route::get('seals/{seal}/check-availability', [SealController::class, 'checkAvailability']);
            Route::get('seals/{seal}/status-history', [SealController::class, 'statusHistory']);


            // Trips
            Route::apiResource('trips', TripController::class)->except('destroy');
            Route::post('trips/{trip}/start', [TripController::class, 'start']);
            Route::patch('trips/{trip}/seal', [TripController::class, 'changeSeal']);
            Route::post('trips/{trip}/confirm-epod', [TripController::class, 'confirmEpod']);
            Route::post('trips/{trip}/vessel-info', [TripController::class, 'addVesselInfo']);
            Route::get('trips/{trip}/seal-status', [TripController::class, 'sealStatus']);
            Route::get('trips/{trip}/timeline', [TripController::class, 'timeline']);
            // Vehicle Tracking
            Route::get('trips/{trip}/tracking', [TripTrackingController::class, 'history']);
            Route::get('trips/{trip}/tracking/latest', [TripTrackingController::class, 'latest']);
            Route::post('trips/{trip}/location', [TripTrackingController::class, 'pushLocation']);

            // Trip Segments
            Route::get('trips/{trip}/segments', [TripController::class, 'segments']);
            Route::post('trips/{trip}/segments', [TripController::class, 'storeSegment']);
            Route::put('trips/{trip}/segments/{segment}', [TripController::class, 'updateSegment']);
            Route::delete('trips/{trip}/segments/{segment}', [TripController::class, 'destroySegment']);

            // Trip Documents
            Route::get('trips/{trip}/documents', [TripDocumentController::class, 'index']);
            Route::post('trips/{trip}/documents', [TripDocumentController::class, 'store']);
            Route::delete('trips/{trip}/documents/{document}', [TripDocumentController::class, 'destroy']);

            // Dashboard — single endpoint, branches by user type inside controller
            Route::get('dashboard/stats', [DashboardController::class, 'stats']);

            // Reports (IL only — permission gate in controller)
            Route::get('reports/trips', [DashboardController::class, 'tripsReport']);
            Route::get('reports/seals', [DashboardController::class, 'sealsReport']);
            Route::get('reports/orders', [DashboardController::class, 'ordersReport']);
        });

        Route::prefix('sepio-inspector')
            ->middleware('can:inspect-sepio')
            ->group(function () {
                Route::get('/me', [SepioInspectorController::class, 'me']);
                Route::get('/customers', [SepioInspectorController::class, 'customers']);
                Route::post('/proxy', [SepioInspectorController::class, 'proxy']);
                Route::post('/proxy-file', [SepioInspectorController::class, 'proxyFile']);
                Route::post('/refresh-token', [SepioInspectorController::class, 'refreshToken']);
            });
    });

    Route::post('tracking/driver-mobile', [TripTrackingController::class, 'driverPush']);

    Route::post("commands", CommandController::class);
});
