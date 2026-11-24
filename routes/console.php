<?php

use Illuminate\Support\Facades\Schedule;

// Check every active source at the configured times (RMM_CHECK_TIMES in RMM_TIMEZONE).
foreach (config('rmm.check_times') as $time) {
    Schedule::command('rmm:check')
        ->dailyAt($time)
        ->timezone(config('rmm.timezone'))
        ->name('rmm-check-'.$time)
        ->withoutOverlapping(60);
}

// Retention: remove media older than RMM_RETENTION_DAYS.
Schedule::command('rmm:cleanup')->hourly()->name('rmm-cleanup')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=336')->daily();
