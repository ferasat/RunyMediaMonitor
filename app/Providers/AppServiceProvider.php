<?php

namespace App\Providers;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Infrastructure\Adapters\WebRssAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdapterRegistry::class, function () {
            $registry = new AdapterRegistry();
            $registry->register(new WebRssAdapter());
            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}