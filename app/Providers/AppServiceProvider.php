<?php

namespace App\Providers;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Infrastructure\Adapters\BaleAdapter;
use App\Infrastructure\Adapters\InstagramAdapter;
use App\Infrastructure\Adapters\TelegramAdapter;
use App\Infrastructure\Adapters\WebRssAdapter;
use App\Infrastructure\Adapters\XAdapter;
use App\Infrastructure\Adapters\YouTubeAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdapterRegistry::class, function () {
            $registry = new AdapterRegistry();
            $registry->register(new WebRssAdapter());
            $registry->register(new YouTubeAdapter());
            $registry->register(new InstagramAdapter());
            $registry->register(new TelegramAdapter());
            $registry->register(new BaleAdapter());
            $registry->register(new XAdapter());
            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}