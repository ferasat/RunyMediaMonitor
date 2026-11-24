<?php

namespace App\Console\Commands;

use App\Services\BaleBotHandler;
use App\Services\BaleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RmmBalePoll extends Command
{
    protected $signature = 'rmm:bale-poll {--max-time=3600 : exit after N seconds (systemd restarts it)} {--once : one getUpdates round}';
    protected $description = 'Long-poll Bale getUpdates for @Insta_youtube_bot and handle /new_link + links (offset persisted)';

    public function handle(BaleClient $bale, BaleBotHandler $handler): int
    {
        $started = time();
        $key = 'rmm-bale-offset';
        if (! Cache::has($key)) {
            // first start: skip old backlog (anything older than 10 minutes)
            Cache::forever($key, 0);
            $this->skipOld($bale, $key);
        }
        do {
            try {
                $updates = $bale->getUpdates((int) Cache::get($key, 0), $this->option('once') ? 1 : 25);
                foreach ($updates as $u) {
                    Cache::forever($key, (int) $u['update_id'] + 1); // persist before handling: never re-handle on crash
                    try {
                        $action = $handler->handle($u);
                        $this->line(now()->format('H:i:s')." update {$u['update_id']}: ".($action ?? 'ignored'));
                    } catch (\Throwable $e) {
                        Log::warning('bale bot handler error', ['error' => $e->getMessage()]);
                        $chat = $u['message']['chat']['id'] ?? null;
                        if ((string) $chat === (string) config('rmm.bale.chat_id')) {
                            try { $bale->reply((string) $chat, '❌ خطای داخلی در پردازش پیام. لطفاً دوباره امتحان کنید.'); } catch (\Throwable) {}
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('bale getUpdates failed', ['error' => $e->getMessage()]);
                sleep(10);
            }
        } while (! $this->option('once') && time() - $started < (int) $this->option('max-time'));
        return self::SUCCESS;
    }

    protected function skipOld(BaleClient $bale, string $key): void
    {
        try {
            foreach ($bale->getUpdates(0, 1) as $u) {
                $date = $u['message']['date'] ?? $u['edited_message']['date'] ?? time();
                if ($date < time() - 600) {
                    Cache::forever($key, (int) $u['update_id'] + 1);
                }
            }
        } catch (\Throwable) {
        }
    }
}
