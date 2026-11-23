<?php

namespace Tests\Unit;

use App\Infrastructure\Services\RssParser;
use PHPUnit\Framework\TestCase;

class RssParserTest extends TestCase
{
    public function test_parses_rss_items(): void
    {
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Hello</title><link>https://example.com/1</link><guid>g1</guid><pubDate>Mon, 01 Jan 2024 00:00:00 GMT</pubDate></item></channel></rss>';
        $items = (new RssParser())->parse($xml);
        $this->assertCount(1, $items);
        $this->assertSame('Hello', $items[0]['title']);
        $this->assertSame('g1', $items[0]['guid']);
    }

    public function test_parses_atom_entries(): void
    {
        $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Atom</title><id>a1</id><link href="https://example.com/a" rel="alternate"/><summary>sum</summary><published>2024-01-01T00:00:00Z</published></entry></feed>';
        $items = (new RssParser())->parse($xml);
        $this->assertCount(1, $items);
        $this->assertSame('Atom', $items[0]['title']);
    }

    public function test_invalid_xml_returns_empty(): void
    {
        $this->assertSame([], (new RssParser())->parse('not xml'));
    }
}