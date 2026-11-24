<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Models\Source;
use App\Services\MediaWorker;

class YouTubeAdapter implements SourceAdapter
{
    public function __construct(protected ?MediaWorker $worker = null) {}

    public function type(): string { return 'youtube'; }

    public function validate(Source $source): bool
    {
        return ! empty($source->url) || ! empty($source->external_id);
    }

    public function fetchMetadata(Source $source): array
    {
        return ['url' => $source->url, 'name' => $source->name];
    }

    /** Scan the newest pages (rmm.pages * page size) via the Python worker. */
    public function discoverNewContent(Source $source): array
    {
        $limit = max(1, (int) config('rmm.pages')) * max(1, (int) config('rmm.youtube_page_size'));
        $worker = $this->worker ?? app(MediaWorker::class);
        $res = $worker->discover('youtube', $source->url ?: ("https://www.youtube.com/channel/".$source->external_id), $limit);
        return $res['items'] ?? [];
    }

    public function fetchContentMetadata(array $item): array
    {
        return array_merge($item, ['fetched_at' => now()->toIso8601String()]);
    }

    public function capabilities(): array
    {
        return ['channel', 'videos', 'shorts', 'download'];
    }

    public function handleError(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('YouTubeAdapter error', ['message' => $e->getMessage()]);
    }
}
