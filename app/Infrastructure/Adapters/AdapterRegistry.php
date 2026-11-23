<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Monitoring\Contracts\SourceAdapter;
use InvalidArgumentException;

class AdapterRegistry
{
    /** @var array<string, SourceAdapter> */
    protected array $adapters = [];

    public function register(SourceAdapter $adapter): void
    {
        $this->adapters[$adapter->type()] = $adapter;
    }

    public function get(string $type): SourceAdapter
    {
        if (! isset($this->adapters[$type])) {
            throw new InvalidArgumentException("No adapter registered for type: {$type}");
        }
        return $this->adapters[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->adapters[$type]);
    }

    /** @return array<string, SourceAdapter> */
    public function all(): array
    {
        return $this->adapters;
    }
}