<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use App\Models\Source;

class InstagramAdapter implements SourceAdapter
{
    public function type(): string { return 'instagram'; }
    public function validate(Source $source): bool { return ! empty($source->url); }
    public function fetchMetadata(Source $source): array { return ['url' => $source->url]; }
    public function discoverNewContent(Source $source): array { return []; } // scaffold
    public function fetchContentMetadata(array $item): array { return $item; }
    public function capabilities(): array { return []; }
    public function handleError(\Throwable $e): void { \Illuminate\Support\Facades\Log::error('InstagramAdapter error', ['message' => $e->getMessage()]); }
}
