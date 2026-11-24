<?php

namespace App\Console\Commands;

use App\Jobs\SourceCheckJob;
use App\Models\Source;
use Illuminate\Console\Command;

class RmmCheck extends Command
{
    protected $signature = 'rmm:check {source? : source id (default: all active)} {--sync : run in this process instead of the queue}';
    protected $description = 'Check sources for new posts and queue downloads/Bale delivery';

    public function handle(): int
    {
        $sources = $this->argument('source')
            ? Source::whereKey($this->argument('source'))->get()
            : Source::active()->whereIn('type', ['youtube', 'instagram', 'website'])->get();
        foreach ($sources as $i => $s) {
            if ($this->option('sync')) {
                $r = (new SourceCheckJob($s))->handle(app(\App\Infrastructure\Adapters\AdapterRegistry::class));
                $this->info("#{$s->id} {$s->name}: found={$r['found']} queued={$r['queued']}".($r['baseline'] ? ' (baseline)' : ''));
            } else {
                // stagger sources a little to be gentle with Instagram/YouTube
                SourceCheckJob::dispatch($s)->delay(now()->addSeconds($i * 20));
                $this->line("queued check for #{$s->id} {$s->name}");
            }
        }
        return self::SUCCESS;
    }
}
