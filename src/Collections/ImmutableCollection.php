<?php

namespace Sanovskiy\SimpleObject\Collections;

use Sanovskiy\SimpleObject\Interfaces\CollectionInterface;

class ImmutableCollection extends Collection
{
    public function lock(bool $disallowUnlock = false): static
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function unlock(): static
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function setClassName(string $name): static
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function shift(): mixed
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function pop(): mixed
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function unshift($value): bool
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function reindexByField(bool $reverse = false, string $field = 'Id'): void
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function push($value): bool
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }

    public function clear(): void
    {
        throw new \RuntimeException('ImmutableCollection cannot be modified.');
    }
}