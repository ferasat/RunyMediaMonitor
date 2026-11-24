<?php

namespace App\Console\Commands;

use App\Services\BaleClient;
use Illuminate\Console\Command;

class RmmBaleTest extends Command
{
    protected $signature = 'rmm:bale-test {--text=✅ RunyMediaMonitor: اتصال ربات برقرار است.}';
    protected $description = 'Send a test message to the configured Bale chat';

    public function handle(BaleClient $bale): int
    {
        try {
            $me = $bale->call('getMe');
            $this->line('bot: @'.($me['username'] ?? '?'));
            $r = $bale->sendMessage($this->option('text'));
            $this->info('sent message_id='.($r['message_id'] ?? '?'));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
