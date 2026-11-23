<?php

use App\Jobs\SourceCheckJob;
use App\Models\Source;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 1: dispatch due sources to the queue every minute.
Schedule::call(function () {
    Source::active()->get()->filter(fn ($s) => $s->isDue())->each(
        fn ($s) => SourceCheckJob::dispatch($s)
    );
})->everyMinute()->name('dispatch-due-sources')->withoutOverlapping();