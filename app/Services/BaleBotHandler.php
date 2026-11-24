<?php

namespace App\Services;

use App\Support\MediaLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Handles incoming Bale updates for @Insta_youtube_bot: /new_link and plain links from the owner only. */
class BaleBotHandler
{
    public const ASK = 'لینک پست اینستاگرام یا یوتیوب را بفرست';

    public function __construct(protected BaleClient $bale, protected ManualDownloads $manual) {}

    /** @return string|null action taken (for logs/tests) */
    public function handle(array $update): ?string
    {
        $msg = $update['message'] ?? $update['edited_message'] ?? null;
        if (! $msg) {
            return null;
        }
        $chatId = (string) ($msg['chat']['id'] ?? '');
        $fromId = (string) ($msg['from']['id'] ?? '');
        $text = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
        $mid = $msg['message_id'] ?? null;
        $owner = (string) config('rmm.bale.chat_id');

        if ($chatId !== $owner || ($fromId !== '' && $fromId !== $owner)) {
            Log::info('bale bot: ignored message from unauthorized chat', ['chat' => $chatId]);
            if (Cache::add("rmm-bale-refused-{$chatId}", 1, now()->addDay())) {
                $this->bale->reply($chatId, 'این ربات خصوصی است و فقط به مدیر پاسخ می‌دهد.', $mid);
            }
            return 'refused';
        }

        $awaitKey = "rmm-bale-await-{$chatId}";
        $isCommand = (bool) preg_match('~^/new_link(@\w+)?\b~i', $text);
        $url = $this->firstUrl($text);

        if ($isCommand && ! $url) {
            Cache::put($awaitKey, 1, now()->addMinutes(30));
            $this->bale->reply($chatId, self::ASK, $mid);
            return 'asked';
        }
        if (! $url) {
            if (preg_match('~^/(start|help)\b~i', $text) || ! Cache::has($awaitKey)) {
                $this->bale->reply($chatId, "سلام! برای دانلود، دستور /new_link را بزنید یا مستقیم لینک پست/ریلز اینستاگرام یا ویدیو/شورت یوتیوب را بفرستید.", $mid);
                return 'help';
            }
            $this->bale->reply($chatId, 'لینکی پیدا نکردم. لطفاً لینک پست اینستاگرام یا یوتیوب را بفرستید.', $mid);
            return 'no-link';
        }

        Cache::forget($awaitKey);
        $link = MediaLink::parse($url);
        if (! $link) {
            $this->bale->reply($chatId, "❌ این لینک پشتیبانی نمی‌شود.\nفقط لینک ویدیو/شورت یوتیوب (youtube.com یا youtu.be) یا پست/ریلز اینستاگرام (instagram.com/p/… یا /reel/…) قابل قبول است.", $mid);
            return 'invalid';
        }
        [$content, $busy] = $this->manual->queue($link, 'bale');
        if ($busy) {
            $this->bale->reply($chatId, "⏳ این لینک همین الان در صف/در حال دانلود است (#{$content->id}).", $mid);
            return 'busy';
        }
        $this->bale->reply($chatId, "✅ در صف دانلود قرار گرفت (#{$content->id}).\nبعد از دانلود، فایل (تا ۴۹ مگابایت) و لینک دانلود همین‌جا ارسال می‌شود.", $mid);
        return 'queued';
    }

    protected function firstUrl(string $text): ?string
    {
        return preg_match('~https?://[^\s<>"]+~i', $text, $m) ? rtrim($m[0], '.,;)!؟،') : null;
    }
}
