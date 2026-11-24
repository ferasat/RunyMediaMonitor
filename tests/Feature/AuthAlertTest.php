<?php

namespace Tests\Feature;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Jobs\SourceCheckJob;
use App\Models\Source;
use App\Services\MediaWorker;
use App\Services\WorkerException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_instagram_cookies_alert_bale_once(): void
    {
        config(['rmm.bale.token' => 'T', 'rmm.bale.token_file' => null, 'rmm.bale.chat_id' => '1', 'cache.default' => 'array']);
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);
        $this->app->instance(MediaWorker::class, new class extends MediaWorker {
            public function discover(string $platform, string $url, int $limit): array
            {
                throw new WorkerException('AUTH_REQUIRED', 'HTTP redirect to login page');
            }
        });
        $this->app->forgetInstance(AdapterRegistry::class);
        $source = Source::create(['type' => 'instagram', 'name' => 'NASA', 'url' => 'https://www.instagram.com/nasa/']);

        foreach ([1, 2] as $_) {
            try {
                (new SourceCheckJob($source))->handle(app(AdapterRegistry::class));
                $this->fail('expected exception');
            } catch (WorkerException) {
            }
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], 'کوکی اینستاگرام'));
    }
}
