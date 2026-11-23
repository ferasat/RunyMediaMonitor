<?php

namespace App\Domain\Monitoring\Contracts;

use App\Models\Source;

interface SourceAdapter
{
    public function type(): string;

    public function validate(Source $source): bool;

    public function fetchMetadata(Source $source): array;

    /** @return array<int, array<string, mixed>> */
    public function discoverNewContent(Source $source): array;

    public function fetchContentMetadata(array $item): array;

    public function capabilities(): array;

    public function handleError(\Throwable $e): void;
}