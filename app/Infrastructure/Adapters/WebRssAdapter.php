<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Infrastructure\Services\RssParser;
use App\Models\Source;
use Illuminate\Support\Facades\Http;

class WebRssAdapter implements SourceAdapter
{
    public function __construct(protected RssParser $parser = new RssParser()) {}

    public function type(): string { return 'website'; }

    public function validate(Source $source): bool
    {
        return ! empty($source->url) && filter_var($source->url, FILTER_VALIDATE_URL) !== false;
    }

    public function fetchMetadata(Source $source): array
    {
        return ['url' => $source->url];
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverNewContent(Source $source): array
    {
        if (! $source->url) return [];
        $response = Http::timeout(30)->get($source->url);
        if (! $response->successful()) return [];
        $items = $this->parser->parse($response->body());
        return array_map(fn ($i) => [
            'external_id' => $i['guid'] ?? null,
            'title' => $i['title'] ?? null,
            'description' => $i['description'] ?? null,
            'url' => $i['link'] ?? null,
            'published_at' => $i['pubDate'] ?? null,
            'media_type' => 'article',
        ], $items);
    }

    public function fetchContentMetadata(array $item): array
    {
        return $item;
    }

    public function capabilities(): array
    {
        return ['rss', 'atom', 'sitemap', 'article_extraction'];
    }

    public function handleError(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('WebRssAdapter error', ['message' => $e->getMessage()]);
    }
}