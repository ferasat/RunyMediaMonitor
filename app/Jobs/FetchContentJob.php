<?php

namespace App\Jobs;

use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** @deprecated kept for queued payload compatibility; use SourceCheckJob. */
class FetchContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Source $source) {}

    public function handle(): void
    {
        SourceCheckJob::dispatch($this->source);
    }
}
