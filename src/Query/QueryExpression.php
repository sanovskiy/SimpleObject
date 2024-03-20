<?php

namespace Sanovskiy\SimpleObject\Query;

class QueryExpression implements \Stringable
{

    public function __construct(private readonly string $expression, protected readonly array $bind=[])
    {
    }

    public function __toString(): string
    {
        return $this->getExpression();
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getBind(): array
    {
        return $this->bind;
    }


}