<?php

namespace Tests\Feature;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Jobs\DeliverContentJob;
use App\Jobs\SourceCheckJob;
use App\Models\Content;
use App\Models\Source;
use App\Services\ContentDelivery;
use App\Services\MediaWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/rmm-test-'.uniqid();
        mkdir($this->root, 0777, true);
        config([
            'rmm.media_root' => $this->root,
            'rmm.bale.token' => 'TEST',
            'rmm.bale.token_file' => null,
            'rmm.bale.chat_id' => '1',
            'rmm.bale.max_upload_mb' => 1, // 1 MB threshold for the test
        ]);
    }

    protected function fakeWorker(array $sizes): void
    {
        $this->app->instance(MediaWorker::class, new class($sizes) extends MediaWorker {
            public function __construct(public array $sizes) {}
            public function download(string $platform, string $url, string $outDir): array
            {
                $files = [];
                foreach ($this->sizes as $i => [$kind, $bytes]) {
                    $p = $outDir.'/f'.$i.($kind === 'video' ? '.mp4' : '.jpg');
                    file_put_contents($p, str_repeat('x', $bytes));
                    $files[] = ['path' => $p, 'name' => basename($p), 'size' => $bytes, 'kind' => $kind];
                }
                return ['ok' => true, 'title' => 'T', 'files' => $files];
            }
            public function discover(string $platform, string $url, int $limit): array
            {
                return ['ok' => true, 'items' => [
                    ['external_id' => 'n1', 'url' => 'https://www.youtube.com/watch?v=n1', 'media_type' => 'video'],
                    ['external_id' => 'n2', 'url' => 'https://www.youtube.com/watch?v=n2', 'media_type' => 'video'],
                    ['external_id' => 'n3', 'url' => 'https://www.youtube.com/shorts/n3', 'media_type' => 'video', 'is_short' => true],
                ]];
            }
        });
        $this->app->forgetInstance(AdapterRegistry::class);
    }

    public function test_small_video_sent_as_file_with_link_and_big_one_as_link_only(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->fakeWorker([['video', 1000], ['video', 2 * 1048576]]);
        $source = Source::create(['type' => 'youtube', 'name' => 'Chan', 'url' => 'https://www.youtube.com/@x']);
        $content = Content::create(['source_id' => $source->id, 'external_id' => 'v1', 'canonical_url' => 'https://www.youtube.com/watch?v=v1', 'status' => 'pending']);

        $c = app(ContentDelivery::class)->deliver($content);

        $this->assertSame('sent', $c->status);
        $this->assertSame(['file+link', 'link'], $c->files()->orderBy('id')->pluck('delivery')->all());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendVideo'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], '/d/'));
        $this->assertCount(2, Http::recorded());
    }

    public function test_download_link_is_signed_and_expires(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->fakeWorker([['photo', 500]]);
        $source = Source::create(['type' => 'instagram', 'name' => 'ig', 'url' => 'https://www.instagram.com/ig/']);
        $content = Content::create(['source_id' => $source->id, 'external_id' => 'p1', 'canonical_url' => 'https://www.instagram.com/p/p1/', 'status' => 'pending']);
        $c = app(ContentDelivery::class)->deliver($content);
        $file = $c->files()->first();

        $this->get($file->downloadUrl())->assertOk();
        $this->get(route('media.download', ['token' => $file->token, 'name' => $file->name]))->assertForbidden();

        $file->update(['expires_at' => now()->subMinute()]);
        $this->artisan('rmm:cleanup')->assertSuccessful();
        $this->assertFileDoesNotExist($file->absolutePath());
        $this->assertSame('expired', $c->fresh()->status);
    }

    public function test_first_check_is_baseline_and_later_items_are_delivered(): void
    {
        Queue::fake();
        $this->fakeWorker([]);
        $source = Source::create(['type' => 'youtube', 'name' => 'Chan', 'url' => 'https://www.youtube.com/@x']);

        (new SourceCheckJob($source, 1))->handle(app(AdapterRegistry::class));
        $this->assertSame(1, Content::where('status', 'pending')->count());
        $this->assertSame(2, Content::where('status', 'seen')->count());
        Queue::assertPushed(DeliverContentJob::class, 1);

        // second run: nothing new
        (new SourceCheckJob($source->fresh()))->handle(app(AdapterRegistry::class));
        Queue::assertPushed(DeliverContentJob::class, 1);
        $this->assertNotNull($source->fresh()->baselined_at);
    }
}
