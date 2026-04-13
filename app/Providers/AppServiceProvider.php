<?php

namespace App\Providers;

use App\Models\SealPricingTier;
use App\Models\User;
use App\Policies\SealPricingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('tenant.customer_id', function () {
            return null;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(SealPricingTier::class, SealPricingPolicy::class);

        Gate::define('inspect-sepio', function (User $user) {
            return $user->hasPermission('sepio.inspect');
        });
    }
}
