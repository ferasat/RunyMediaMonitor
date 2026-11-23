<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Models\Source;

class YouTubeAdapter implements SourceAdapter
{
    public function type(): string { return 'youtube'; }

    public function validate(Source $source): bool
    {
        return ! empty($source->external_id) || ! empty($source->url);
    }

    public function fetchMetadata(Source $source): array
    {
        return [
            'channel_id' => $source->external_id,
            'title' => $source->name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function discoverNewContent(Source $source): array
    {
        // Scaffold: returns mock items; real YouTube Data API v3 integration later.
        return [
            [
                'external_id' => 'vid_' . uniqid(),
                'title' => 'Sample YouTube Video',
                'description' => 'Sample description',
                'url' => 'https://www.youtube.com/watch?v=sample',
                'published_at' => now()->toIso8601String(),
                'media_type' => 'video',
                'thumbnail' => 'https://i.ytimg.com/vi/sample/hqdefault.jpg',
                'duration' => 120,
                'is_short' => false,
                'is_live' => false,
            ],
        ];
    }

    public function fetchContentMetadata(array $item): array
    {
        return array_merge($item, ['fetched_at' => now()->toIso8601String()]);
    }

    public function capabilities(): array
    {
        return ['channel', 'playlist', 'video', 'shorts', 'live', 'thumbnail', 'transcript'];
    }

    public function handleError(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('YouTubeAdapter error', ['message' => $e->getMessage()]);
    }
}