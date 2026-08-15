<?php

namespace App\Providers;

use App\Services\Api\ApiClient;
use App\Services\Api\FixtureApiClient;
use App\Services\Api\HttpApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so FixtureApiClient's in-memory check-in state persists
        // across resolutions within one request/test.
        $this->app->singleton(ApiClient::class, function ($app) {
            return match ($app['config']['ticketscanner.api']) {
                'http' => $app->make(HttpApiClient::class),
                default => $app->make(FixtureApiClient::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
