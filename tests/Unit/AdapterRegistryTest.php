<?php

namespace Tests\Unit;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Infrastructure\Adapters\WebRssAdapter;
use PHPUnit\Framework\TestCase;

class AdapterRegistryTest extends TestCase
{
    public function test_register_and_get(): void
    {
        $r = new AdapterRegistry();
        $a = new WebRssAdapter();
        $r->register($a);
        $this->assertTrue($r->has('website'));
        $this->assertSame($a, $r->get('website'));
    }

    public function test_unknown_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AdapterRegistry())->get('nope');
    }
}