<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Models\Source;

class InstagramAdapter implements SourceAdapter
{
    public function type(): string { return 'instagram'; }

    public function validate(Source $source): bool
    {
        return ! empty($source->url) || ! empty($source->username);
    }

    public function fetchMetadata(Source $source): array
    {
        return ['username' => $source->username ?: $source->name];
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverNewContent(Source $source): array
    {
        // Scaffold: mock items; real Instagram scraping/API later.
        return [
            [
                'external_id' => 'ig_' . uniqid(),
                'title' => 'پست اینستاگرام: ' . $source->name,
                'description' => 'Caption نمونه',
                'url' => $source->url,
                'published_at' => now()->toIso8601String(),
                'media_type' => 'image',
                'thumbnail' => null,
                'media_url' => 'https://example.com/media/sample.jpg',
            ],
        ];
    }

    public function fetchContentMetadata(array $item): array
    {
        return array_merge($item, ['fetched_at' => now()->toIso8601String()]);
    }

    public function capabilities(): array
    {
        return ['posts', 'reels', 'carousels', 'caption', 'metadata'];
    }

    public function handleError(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('InstagramAdapter error', ['message' => $e->getMessage()]);
    }
}