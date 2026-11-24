<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Support\Jalali;
use Illuminate\Support\Str;

/** Download a content item's media to the server, then deliver it to Bale (49 MB rule). */
class ContentDelivery
{
    public function __construct(protected MediaWorker $worker, protected BaleClient $bale) {}

    public function deliver(Content $content, ?string $chatId = null): Content
    {
        if ($chatId === null && ($content->status === 'sent'
            || ($content->status === 'downloading' && $content->updated_at?->gt(now()->subMinutes(40))))) {
            return $content;
        }
        $content->increment('attempts');
        $content->update(['status' => 'downloading', 'error_message' => null]);

        try {
            $files = $content->files()->get()->filter->isAvailable();
            if ($files->isEmpty()) {
                $files = $this->download($content);
            }
            $content->update(['status' => 'downloaded', 'downloaded_at' => $content->downloaded_at ?? now()]);
            $this->send($content, $files->values()->all(), $chatId);
            $content->update(['status' => 'sent', 'sent_at' => now()]);
            return $content;
        } catch (\Throwable $e) {
            $content->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::warning('rmm delivery failed', ['content_id' => $content->id, 'error' => $e->getMessage()]);
            if ($content->source?->isManual()) {
                try {
                    $this->bale->sendMessage("❌ دانلود ناموفق بود (#{$content->id}):\n{$content->canonical_url}\n"
                        .ManualDownloads::persianError($e->getMessage())."\n".mb_substr($e->getMessage(), 0, 300), $chatId);
                } catch (\Throwable) {
                }
            } elseif ($content->attempts >= 3) {
                try {
                    $this->bale->sendMessage("⚠️ دریافت پست ناموفق بود ({$content->source->name}):\n{$content->canonical_url}\n"
                        .mb_substr($e->getMessage(), 0, 400), $chatId);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /** @return \Illuminate\Support\Collection<int, ContentFile> */
    public function download(Content $content)
    {
        $root = rtrim(config('rmm.media_root'), '/');
        $this->guardDisk($root);
        $rel = $content->source_id.'/'.$content->id.'-'.Str::random(6);
        $dir = $root.'/'.$rel;
        File::ensureDirectoryExists($dir, 0750);

        $res = $this->worker->download($content->source->type, $content->canonical_url, $dir);
        $expires = now()->addDays((int) config('rmm.retention_days'));
        $content->files()->delete();
        $rows = collect();
        foreach ($res['files'] ?? [] as $f) {
            $rows->push($content->files()->create([
                'token' => Str::random(32),
                'path' => $rel.'/'.basename($f['path']),
                'name' => $this->niceName($content, $f['name']),
                'kind' => $f['kind'] ?? 'document',
                'size' => (int) ($f['size'] ?? 0),
                'width' => $f['width'] ?? null,
                'height' => $f['height'] ?? null,
                'duration' => isset($f['duration']) ? (int) $f['duration'] : null,
                'expires_at' => $expires,
            ]));
        }
        $update = [];
        if (! $content->title && ! empty($res['title'])) $update['title'] = $res['title'];
        if (! $content->body && ! empty($res['description'])) $update['body'] = $res['description'];
        if (! empty($res['published_at']) && (! $content->published_at || $content->published_at->copy()->setTimezone('UTC')->format('H:i:s') === '00:00:00')) {
            $update['published_at'] = gmdate('Y-m-d H:i:s', strtotime($res['published_at']));
        }
        if ($update) $content->update($update);
        return $rows;
    }

    /** @param ContentFile[] $files */
    public function send(Content $content, array $files, ?string $chatId = null): void
    {
        $maxBytes = (int) (config('rmm.bale.max_upload_mb') * 1048576);
        $maxPhoto = (int) (config('rmm.bale.max_photo_mb') * 1048576);
        $total = count($files);
        $sent = [];

        foreach (array_values($files) as $i => $file) {
            $link = $file->downloadUrl();
            $num = $total > 1 ? ' ('.($i + 1)."/{$total})" : '';
            $sizeLine = "📦 حجم: {$file->sizeMb()} MB{$num}";
            $linkLine = "⬇️ لینک دانلود (اعتبار ".config('rmm.link_days')." روز):\n{$link}";
            $tooBig = $file->size > $maxBytes;
            $note = $tooBig ? "ℹ️ فایل بزرگ‌تر از ".config('rmm.bale.max_upload_mb')." مگابایت است؛ فقط لینک ارسال شد." : '';
            $caption = $this->caption($content, $i === 0, $sizeLine."\n".$linkLine, $note, $tooBig ? 4000 : (int) config('rmm.bale.caption_limit', 1024));

            if ($tooBig) {
                $r = $this->bale->sendMessage($caption, $chatId);
                $file->update(['delivery' => 'link']);
                $sent[] = ['file' => $file->id, 'method' => 'sendMessage', 'message_id' => $r['message_id'] ?? null];
                continue;
            }
            $path = $file->absolutePath();
            $method = null;
            if ($file->kind === 'video') {
                try {
                    $method = 'sendVideo';
                    $r = $this->bale->sendFile('sendVideo', 'video', $path, $caption, $chatId, array_filter([
                        'width' => $file->width, 'height' => $file->height, 'duration' => $file->duration,
                        'supports_streaming' => 'true',
                    ]));
                } catch (\Throwable $e) {
                    Log::info('sendVideo failed, falling back to sendDocument', ['error' => $e->getMessage()]);
                    $method = 'sendDocument';
                    $r = $this->bale->sendFile('sendDocument', 'document', $path, $caption, $chatId);
                }
            } elseif ($file->kind === 'photo' && $file->size <= $maxPhoto) {
                $method = 'sendPhoto';
                $r = $this->bale->sendFile('sendPhoto', 'photo', $path, $caption, $chatId);
            } else {
                $method = 'sendDocument';
                $r = $this->bale->sendFile('sendDocument', 'document', $path, $caption, $chatId);
            }
            $file->update(['delivery' => 'file+link']);
            $sent[] = ['file' => $file->id, 'method' => $method, 'message_id' => $r['message_id'] ?? null];
        }
        $meta = $content->metadata ?? [];
        $meta['bale'] = ['chat_id' => $chatId ?? $this->bale->chatId(), 'sent_at' => now()->toIso8601String(), 'messages' => $sent];
        $content->update(['metadata' => $meta]);
    }

    /** Refuse to download when the disk is nearly full or the media dir is over its cap; alert Bale (throttled). */
    public function guardDisk(string $root): void
    {
        File::ensureDirectoryExists($root, 0750);
        $freeGb = @disk_free_space($root) / 1073741824;
        $usedGb = $this->dirSizeGb($root);
        $minFree = (float) config('rmm.min_free_gb');
        $maxMedia = (float) config('rmm.max_media_gb');
        if (($minFree > 0 && $freeGb < $minFree) || ($maxMedia > 0 && $usedGb > $maxMedia)) {
            $msg = sprintf('فضای دیسک کافی نیست (آزاد: %.1f GB، حداقل: %.0f GB؛ حجم رسانه‌ها: %.1f GB از %.0f GB). دانلودها موقتاً متوقف شد.',
                $freeGb, $minFree, $usedGb, $maxMedia);
            if (\Illuminate\Support\Facades\Cache::add('rmm-disk-alert', 1, now()->addHours(6))) {
                try { $this->bale->sendMessage('💾 '.$msg); } catch (\Throwable) {}
            }
            throw new WorkerException('DISK_LOW', $msg);
        }
    }

    protected function dirSizeGb(string $root): float
    {
        return \Illuminate\Support\Facades\Cache::remember('rmm-media-size', 300, function () use ($root) {
            $bytes = 0;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $bytes += $f->getSize();
            }
            return $bytes / 1073741824;
        });
    }

    /**
     * Caption: header (first item) with Jalali publish/send dates + size/link. The post text is the only part
     * that gets truncated, so dates and links always survive the Bale caption limit.
     */
    public function caption(Content $content, bool $withHeader, string $tail, string $note = '', int $limit = 1024): string
    {
        $src = $content->source;
        $icon = $src->type === 'youtube' ? ($content->is_short ? '🎬 YouTube Shorts' : '▶️ YouTube') : '📸 Instagram';
        if ($src->isManual()) {
            $icon = '📥 '.$icon;
        }
        $dates = '📅 تاریخ انتشار: '.Jalali::published($content->published_at)."\n"
            .'📤 تاریخ ارسال: '.Jalali::format(now());
        $top = $withHeader ? "{$icon} | {$src->name}" : '';
        $bottom = trim(($withHeader ? "🔗 {$content->canonical_url}\n" : '')."{$dates}\n\n{$tail}".($note !== '' ? "\n\n{$note}" : ''));
        $text = $withHeader ? trim(preg_replace('/\s+/u', ' ', (string) ($content->title ?: $content->body))) : '';
        $build = fn (string $t) => trim(($top !== '' ? $top."\n" : '').($t !== '' ? $t."\n" : '').$bottom);
        $budget = min(300, $limit - mb_strlen($build('')) - 2);
        $text = $budget > 10 ? Str::limit($text, $budget - 1, '…') : '';
        return mb_substr($build($text), 0, $limit);
    }

    protected function niceName(Content $content, string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = Str::slug(Str::limit($content->source->name, 30, ''), '_') ?: 'media';
        $suffix = pathinfo($name, PATHINFO_FILENAME);
        $id = $content->external_id ?: (string) $content->id;
        $stem = $suffix === $id ? "{$base}_{$id}" : "{$base}_{$id}_{$suffix}";
        return $stem.($ext ? ".{$ext}" : '');
    }
}
