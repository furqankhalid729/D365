<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use Shopify\Auth\FileSessionStorage;

class ShopifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Context::initialize(
            apiKey: env('SHOPIFY_API_KEY'),
            apiSecretKey: env('SHOPIFY_SECERT_KEY'),
            scopes: env('SCOPE'),
            hostName: env('APP_HOST_NAME'),
            sessionStorage: new FileSessionStorage(storage_path('session')),
            apiVersion: '2025-01',
            isEmbeddedApp: false,
            isPrivateApp: false,
        );
    }
}
