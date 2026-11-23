<?php

namespace App\Jobs;

use App\Infrastructure\Services\RssParser;
use App\Models\Content;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Source $source) {}

    public function handle(RssParser $parser): void
    {
        if (! $this->source->url) {
            return;
        }
        try {
            $response = Http::timeout(30)->get($this->source->url);
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP ' . $response->status());
            }
            $items = $parser->parse($response->body());
            $newCount = 0;
            foreach ($items as $item) {
                $hash = hash('sha256', ($item['guid'] ?? '') . '|' . ($item['link'] ?? ''));
                $exists = Content::where('source_id', $this->source->id)
                    ->where(function ($q) use ($item, $hash) {
                        $q->where('external_id', $item['guid'] ?? null)
                          ->orWhere('content_hash', $hash)
                          ->orWhere('canonical_url', $item['link'] ?? null);
                    })->exists();
                if ($exists) {
                    continue;
                }
                Content::create([
                    'source_id' => $this->source->id,
                    'external_id' => $item['guid'] ?? null,
                    'title' => $item['title'] ?? null,
                    'body' => $item['description'] ?? null,
                    'canonical_url' => $item['link'] ?? null,
                    'content_hash' => $hash,
                    'media_type' => 'article',
                    'published_at' => ! empty($item['pubDate']) ? date('Y-m-d H:i:s', strtotime($item['pubDate'])) : null,
                    'metadata' => $item,
                ]);
                $newCount++;
            }
            Log::info('FetchContentJob done', ['source_id' => $this->source->id, 'new' => $newCount]);
        } catch (\Throwable $e) {
            Log::error('FetchContentJob failed', ['source_id' => $this->source->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}