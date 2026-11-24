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
            : Source::active()->monitored()->whereIn('type', ['youtube', 'instagram', 'website'])->orderBy('id')->get();
        $gap = max(0, (int) config('rmm.source_spacing_seconds'));
        foreach ($sources as $i => $s) {
            if ($this->option('sync')) {
                $r = (new SourceCheckJob($s))->handle(app(\App\Infrastructure\Adapters\AdapterRegistry::class));
                $this->info("#{$s->id} {$s->name}: found={$r['found']} queued={$r['queued']}".($r['baseline'] ? ' (baseline)' : ''));
            } else {
                // spread the checks over time (RMM_SOURCE_SPACING seconds apart) to keep load and request rate low
                SourceCheckJob::dispatch($s)->delay(now()->addSeconds($i * $gap));
                $this->line("queued check for #{$s->id} {$s->name}");
            }
        }
        return self::SUCCESS;
    }
}
