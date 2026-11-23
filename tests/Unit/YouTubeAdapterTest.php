<?php

namespace Tests\Unit;

use App\Infrastructure\Adapters\YouTubeAdapter;
use App\Models\Source;
use PHPUnit\Framework\TestCase;

class YouTubeAdapterTest extends TestCase
{
    public function test_discover_returns_items(): void
    {
        $adapter = new YouTubeAdapter();
        $source = new Source(['type' => 'youtube', 'name' => 'Chan', 'external_id' => 'UC123']);
        $items = $adapter->discoverNewContent($source);
        $this->assertNotEmpty($items);
        $this->assertSame('video', $items[0]['media_type']);
        $this->assertContains('transcript', $adapter->capabilities());
    }

    public function test_validate_requires_id_or_url(): void
    {
        $adapter = new YouTubeAdapter();
        $this->assertFalse($adapter->validate(new Source(['type' => 'youtube'])));
        $this->assertTrue($adapter->validate(new Source(['type' => 'youtube', 'external_id' => 'UC1'])));
    }
}