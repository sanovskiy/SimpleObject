<?php

namespace Sanovskiy\SimpleObject\Traits;

use BadMethodCallException;

trait ActiveRecordIteratorTrait
{
    /**
     * Implementation of Iterator, ArrayAccess, Countable interfaces.
     */

    public function rewind(): void
    {
        reset(static::$propertiesMapping);
    }

    public function current(): mixed
    {
        $property = current(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            return $this->__get($property);
        }
        return false;
    }

    public function next(): void
    {
        next(static::$propertiesMapping);
    }

    public function valid(): bool
    {
        return $this->key() !== null;
    }

    public function key(): null|int|string|bool
    {
        return key(static::$propertiesMapping);
    }

    public function offsetExists(mixed $offset): bool
    {
        return (static::isPropertyExist($offset) || static::isTableFieldExist($offset));
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (static::isPropertyExist($offset) || static::isTableFieldExist($offset)) {
            return $this->__get($offset);
        }
        return false;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Unsetting properties is not supported');
    }

    public function count(): int
    {
        return count(static::$propertiesMapping);
    }
}