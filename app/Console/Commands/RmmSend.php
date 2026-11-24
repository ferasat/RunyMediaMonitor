<?php

namespace App\Console\Commands;

use App\Jobs\DeliverContentJob;
use App\Models\Content;
use App\Services\ContentDelivery;
use Illuminate\Console\Command;

class RmmSend extends Command
{
    protected $signature = 'rmm:send {content : content id} {--sync} {--force : resend even if already sent}';
    protected $description = 'Download (if needed) and deliver one content item to Bale';

    public function handle(ContentDelivery $delivery): int
    {
        $c = Content::findOrFail($this->argument('content'));
        if ($this->option('force') || in_array($c->status, ['seen', 'failed', 'expired'])) {
            $c->update(['status' => 'pending', 'attempts' => 0]);
        }
        if ($this->option('sync')) {
            $c = $delivery->deliver($c->fresh());
            foreach ($c->files as $f) {
                $this->line("{$f->kind} {$f->sizeMb()}MB delivery={$f->delivery} {$f->downloadUrl()}");
            }
            $this->info("status={$c->status}");
        } else {
            DeliverContentJob::dispatch($c);
            $this->info('queued');
        }
        return self::SUCCESS;
    }
}
