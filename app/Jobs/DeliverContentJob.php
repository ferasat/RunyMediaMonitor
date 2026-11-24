<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\ContentDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;          // attempts are tracked on the content row (max 3, retried on next check)
    public int $timeout = 1850;

    public function __construct(public Content $content) {}

    public function handle(ContentDelivery $delivery): void
    {
        $delivery->deliver($this->content->fresh());
    }
}
