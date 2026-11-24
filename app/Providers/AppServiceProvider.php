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
            $registry->register(new YouTubeAdapter(app(\App\Services\MediaWorker::class)));
            $registry->register(new InstagramAdapter(app(\App\Services\MediaWorker::class)));
            $registry->register(new TelegramAdapter());
            $registry->register(new BaleAdapter());
            $registry->register(new XAdapter());
            return $registry;
        });
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }
    }
}