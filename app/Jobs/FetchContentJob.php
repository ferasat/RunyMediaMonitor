<?php

namespace App\Jobs;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Models\Content;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Source $source) {}

    public function handle(AdapterRegistry $registry): void
    {
        if (! $registry->has($this->source->type)) {
            Log::warning('No adapter for source type', ['type' => $this->source->type]);
            return;
        }
        $adapter = $registry->get($this->source->type);
        if (! $adapter->validate($this->source)) {
            Log::warning('Source validation failed', ['source_id' => $this->source->id]);
            return;
        }

        $items = $adapter->discoverNewContent($this->source);
        $newCount = 0;
        foreach ($items as $item) {
            $externalId = $item['external_id'] ?? null;
            $url = $item['url'] ?? null;
            $hash = hash('sha256', ($externalId ?? '') . '|' . ($url ?? ''));

            $exists = Content::where('source_id', $this->source->id)
                ->where(function ($q) use ($externalId, $url, $hash) {
                    if ($externalId) $q->orWhere('external_id', $externalId);
                    if ($url) $q->orWhere('canonical_url', $url);
                    $q->orWhere('content_hash', $hash);
                })->exists();
            if ($exists) continue;

            Content::create([
                'source_id' => $this->source->id,
                'external_id' => $externalId,
                'title' => $item['title'] ?? null,
                'body' => $item['description'] ?? null,
                'caption' => $item['caption'] ?? null,
                'canonical_url' => $url,
                'content_hash' => $hash,
                'media_type' => $item['media_type'] ?? 'unknown',
                'published_at' => ! empty($item['published_at']) ? date('Y-m-d H:i:s', strtotime($item['published_at'])) : null,
                'metadata' => $adapter->fetchContentMetadata($item),
            ]);
            $newCount++;
        }
        $this->source->update(['last_checked_at' => now(), 'last_successful_check_at' => now(), 'last_error' => null]);
        Log::info('FetchContentJob done', ['source_id' => $this->source->id, 'new' => $newCount]);
    }
}