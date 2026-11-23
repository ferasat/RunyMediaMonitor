<?php

namespace App\Jobs;

use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SourceCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Source $source) {}

    public function handle(): void
    {
        $this->source->update(['last_checked_at' => now()]);

        try {
            // Adapter resolution + discovery will be wired in Phase 2+.
            Log::info('Source checked', ['source_id' => $this->source->id, 'type' => $this->source->type]);
            $this->source->update(['last_successful_check_at' => now(), 'last_error' => null]);
        } catch (\Throwable $e) {
            $this->source->update(['last_error' => $e->getMessage()]);
            throw $e;
        }
    }
}