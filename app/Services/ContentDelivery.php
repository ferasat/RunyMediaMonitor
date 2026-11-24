<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
            if ($content->attempts >= 3) {
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
        if (! $content->published_at && ! empty($res['published_at'])) $update['published_at'] = date('Y-m-d H:i:s', strtotime($res['published_at']));
        if ($update) $content->update($update);
        return $rows;
    }

    /** @param ContentFile[] $files */
    public function send(Content $content, array $files, ?string $chatId = null): void
    {
        $maxBytes = (int) (config('rmm.bale.max_upload_mb') * 1048576);
        $maxPhoto = (int) (config('rmm.bale.max_photo_mb') * 1048576);
        $header = $this->header($content);
        $total = count($files);

        foreach (array_values($files) as $i => $file) {
            $link = $file->downloadUrl();
            $num = $total > 1 ? ' ('.($i + 1)."/{$total})" : '';
            $sizeLine = "📦 حجم: {$file->sizeMb()} MB{$num}";
            $linkLine = "⬇️ لینک دانلود (اعتبار ".config('rmm.link_days')." روز):\n{$link}";
            $caption = ($i === 0 ? $header."\n\n" : '').$sizeLine."\n".$linkLine;

            $tooBig = $file->size > $maxBytes;
            if ($tooBig) {
                $this->bale->sendMessage($caption."\n\nℹ️ فایل بزرگ‌تر از ".config('rmm.bale.max_upload_mb')." مگابایت است؛ فقط لینک ارسال شد.", $chatId);
                $file->update(['delivery' => 'link']);
                continue;
            }
            $path = $file->absolutePath();
            if ($file->kind === 'video') {
                try {
                    $this->bale->sendFile('sendVideo', 'video', $path, $caption, $chatId, array_filter([
                        'width' => $file->width, 'height' => $file->height, 'duration' => $file->duration,
                        'supports_streaming' => 'true',
                    ]));
                } catch (\Throwable $e) {
                    Log::info('sendVideo failed, falling back to sendDocument', ['error' => $e->getMessage()]);
                    $this->bale->sendFile('sendDocument', 'document', $path, $caption, $chatId);
                }
            } elseif ($file->kind === 'photo' && $file->size <= $maxPhoto) {
                $this->bale->sendFile('sendPhoto', 'photo', $path, $caption, $chatId);
            } else {
                $this->bale->sendFile('sendDocument', 'document', $path, $caption, $chatId);
            }
            $file->update(['delivery' => 'file+link']);
        }
    }

    protected function header(Content $content): string
    {
        $src = $content->source;
        $icon = $src->type === 'youtube' ? ($content->is_short ? '🎬 YouTube Shorts' : '▶️ YouTube') : '📸 Instagram';
        $text = trim((string) ($content->title ?: $content->body));
        $text = Str::limit(preg_replace('/\s+/u', ' ', $text), 300);
        $date = $content->published_at?->timezone(config('rmm.timezone'))->format('Y-m-d H:i');
        return trim("{$icon} | {$src->name}\n".($text !== '' ? $text."\n" : '').($date ? "🕒 {$date}\n" : '')."🔗 {$content->canonical_url}");
    }

    protected function niceName(Content $content, string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = Str::slug(Str::limit($content->source->name, 30, ''), '_') ?: 'media';
        $suffix = pathinfo($name, PATHINFO_FILENAME);
        return "{$base}_{$content->external_id}_{$suffix}".($ext ? ".{$ext}" : '');
    }
}
