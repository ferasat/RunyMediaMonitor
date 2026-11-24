<?php

namespace App\Services;

use App\Jobs\DeliverContentJob;
use App\Models\Content;
use App\Models\Source;
use App\Support\MediaLink;

/** Shared single-link pipeline used by the panel page and the Bale bot. */
class ManualDownloads
{
    /** @return array{0: Content, 1: bool} [content, alreadyInProgress] */
    public function queue(MediaLink $link, string $via = 'panel'): array
    {
        $source = Source::manualFor($link->platform);
        $content = Content::withTrashed()->firstOrNew(['source_id' => $source->id, 'external_id' => $link->id]);
        if ($content->exists && ! $content->trashed() && in_array($content->status, ['pending', 'downloading', 'downloaded'], true)
            && $content->updated_at?->gt(now()->subMinutes(40))) {
            return [$content, true];
        }
        $content->fill([
            'canonical_url' => $link->url,
            'content_hash' => hash('sha256', $link->id.'|'.$link->url),
            'media_type' => $link->platform === 'instagram' ? ($link->kind === 'reel' ? 'video' : 'unknown') : 'video',
            'is_short' => $link->isShort,
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null,
            'metadata' => array_merge($content->metadata ?? [], ['manual' => true, 'via' => $via, 'requested_at' => now()->toIso8601String()]),
        ]);
        $content->deleted_at = null;
        $content->save();
        $content->touch();
        DeliverContentJob::dispatch($content->fresh('source'));
        return [$content, false];
    }

    public static function persianError(string $message): string
    {
        $map = [
            'AUTH_REQUIRED' => 'دسترسی ممکن نشد (پست خصوصی است یا نشست/کوکی اینستاگرام منقضی شده).',
            'INSTAGRAM_CHECKPOINT' => 'اینستاگرام درخواست تأیید امنیتی داده است.',
            'PRIVATE' => 'این پست خصوصی است.',
            'NOT_FOUND' => 'پست پیدا نشد یا حذف شده است.',
            'RATE_LIMITED' => 'محدودیت تعداد درخواست؛ کمی بعد دوباره امتحان کنید.',
            'YOUTUBE_BOT_CHECK' => 'یوتیوب درخواست تأیید ربات داده است.',
            'DISK_LOW' => 'فضای دیسک سرور کم است؛ دانلودها موقتاً متوقف شده‌اند.',
        ];
        foreach ($map as $code => $fa) {
            if (str_contains($message, "[{$code}]")) {
                return $fa;
            }
        }
        return 'خطا در دانلود.';
    }
}
