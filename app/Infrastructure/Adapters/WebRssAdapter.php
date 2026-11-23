<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Models\Source;

class WebRssAdapter implements SourceAdapter
{
    public function type(): string
    {
        return 'website';
    }

    public function validate(Source $source): bool
    {
        return filter_var($source->url, FILTER_VALIDATE_URL) !== false;
    }

    public function fetchMetadata(Source $source): array
    {
        return ['url' => $source->url];
    }

    public function discoverNewContent(Source $source): array
    {
        // Phase 2: parse RSS/Atom/Sitemap.
        return [];
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