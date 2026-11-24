<?php

namespace App\Console\Commands;

use App\Jobs\SourceCheckJob;
use App\Models\Source;
use Illuminate\Console\Command;

class RmmAdd extends Command
{
    protected $signature = 'rmm:add {url} {--name=} {--send-latest=0 : on first scan also deliver the N newest items} {--no-check}';
    protected $description = 'Add a YouTube channel or Instagram page to monitor (first scan = baseline)';

    public function handle(): int
    {
        $url = $this->argument('url');
        $d = Source::detect($url);
        $source = Source::firstOrCreate(['url' => $url], array_merge($d, [
            'name' => $this->option('name') ?: $d['name'],
            'is_active' => true,
            'check_interval_minutes' => 720,
        ]));
        $this->info("source #{$source->id} {$source->type} {$source->name}");
        if (! $this->option('no-check')) {
            SourceCheckJob::dispatch($source, (int) $this->option('send-latest'));
            $this->line('baseline scan queued');
        }
        return self::SUCCESS;
    }
}
