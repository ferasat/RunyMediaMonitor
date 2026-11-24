<?php

namespace Tests\Unit;

use App\Infrastructure\Adapters\YouTubeAdapter;
use App\Models\Source;
use App\Services\MediaWorker;
use Tests\TestCase;

class YouTubeAdapterTest extends TestCase
{
    public function test_discover_uses_worker_with_two_pages(): void
    {
        config(['rmm.pages' => 2, 'rmm.youtube_page_size' => 30]);
        $worker = new class extends MediaWorker {
            public array $calls = [];
            public function discover(string $platform, string $url, int $limit): array
            {
                $this->calls[] = [$platform, $url, $limit];
                return ['ok' => true, 'items' => [['external_id' => 'abc', 'url' => 'https://www.youtube.com/shorts/abc', 'media_type' => 'video', 'is_short' => true]]];
            }
        };
        $adapter = new YouTubeAdapter($worker);
        $items = $adapter->discoverNewContent(new Source(['type' => 'youtube', 'name' => 'Chan', 'url' => 'https://www.youtube.com/@x']));
        $this->assertSame([['youtube', 'https://www.youtube.com/@x', 60]], $worker->calls);
        $this->assertTrue($items[0]['is_short']);
    }

    public function test_validate_requires_id_or_url(): void
    {
        $adapter = new YouTubeAdapter();
        $this->assertFalse($adapter->validate(new Source(['type' => 'youtube'])));
        $this->assertTrue($adapter->validate(new Source(['type' => 'youtube', 'external_id' => 'UC1'])));
    }

    public function test_detect_source_type(): void
    {
        $this->assertSame('youtube', Source::detect('https://www.youtube.com/@TED')['type']);
        $this->assertSame('TED', Source::detect('https://www.youtube.com/@TED/shorts')['name']);
        $this->assertSame('instagram', Source::detect('https://www.instagram.com/nasa/')['type']);
        $this->assertSame('nasa', Source::detect('https://instagram.com/nasa')['username']);
        $this->assertSame('website', Source::detect('https://www.instagram.com/p/ABC/')['type']);
    }
}
