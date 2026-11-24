<?php

namespace App\Jobs;

use App\Infrastructure\Adapters\AdapterRegistry;
use App\Models\Content;
use App\Models\Source;
use App\Models\SourceCheckLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scan one source, store new items, and queue delivery for the new ones.
 * First successful scan of a source is a baseline: everything found is marked
 * "seen" (not delivered) except the newest $sendLatest items.
 */
class SourceCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 300;
    public int $timeout = 900;

    public function __construct(public Source $source, public ?int $sendLatest = null) {}

    public function handle(AdapterRegistry $registry): array
    {
        $source = $this->source->fresh();
        $source->update(['last_checked_at' => now()]);
        $log = SourceCheckLog::create(['source_id' => $source->id, 'status' => 'running', 'started_at' => now()]);

        try {
            $adapter = $registry->get($source->type);
            if (! $adapter->validate($source)) {
                throw new \RuntimeException('source is missing url/identifier');
            }
            $items = $adapter->discoverNewContent($source);
            $first = $source->baselined_at === null;
            $toSend = $first ? max(0, $this->sendLatest ?? (int) config('rmm.initial_send')) : PHP_INT_MAX;
            $new = [];
            foreach ($items as $item) {
                $externalId = $item['external_id'] ?? null;
                $url = $item['url'] ?? null;
                $hash = hash('sha256', ($externalId ?? '').'|'.($url ?? ''));
                $exists = Content::withTrashed()->where('source_id', $source->id)
                    ->where(function ($q) use ($externalId, $url, $hash) {
                        if ($externalId) $q->orWhere('external_id', $externalId);
                        if ($url) $q->orWhere('canonical_url', $url);
                        $q->orWhere('content_hash', $hash);
                    })->exists();
                if ($exists) continue;

                $deliver = count($new) < $toSend;
                $content = Content::create([
                    'source_id' => $source->id,
                    'external_id' => $externalId,
                    'title' => $item['title'] ?? null,
                    'body' => $item['description'] ?? null,
                    'canonical_url' => $url,
                    'content_hash' => $hash,
                    'media_type' => $item['media_type'] ?? 'unknown',
                    'is_short' => (bool) ($item['is_short'] ?? false),
                    'published_at' => ! empty($item['published_at']) ? date('Y-m-d H:i:s', strtotime($item['published_at'])) : null,
                    'metadata' => $adapter->fetchContentMetadata($item),
                    'status' => $deliver ? 'pending' : 'seen',
                ]);
                if ($deliver) {
                    $new[] = $content;
                }
            }
            $source->update([
                'last_successful_check_at' => now(),
                'last_error' => null,
                'baselined_at' => $source->baselined_at ?? now(),
            ]);
            foreach ($new as $content) {
                DeliverContentJob::dispatch($content);
            }
            // retry earlier failures (max 3 attempts)
            Content::where('source_id', $source->id)->where('status', 'failed')->where('attempts', '<', 3)
                ->get()->each(fn ($c) => DeliverContentJob::dispatch($c));

            $summary = ['found' => count($items), 'queued' => count($new), 'baseline' => $first];
            $log->update(['status' => 'success', 'finished_at' => now(),
                'items_discovered' => count($items), 'items_new' => count($new)]);
            Log::info('rmm source checked', ['source_id' => $source->id] + $summary);
            return $summary;
        } catch (\Throwable $e) {
            $source->update(['last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            $log->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
            Log::warning('rmm source check failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(\App\Services\BaleClient::class)->sendMessage(
                "⚠️ بررسی منبع «{$this->source->name}» ناموفق بود:\n".mb_substr($e->getMessage(), 0, 500));
        } catch (\Throwable) {
        }
    }
}
