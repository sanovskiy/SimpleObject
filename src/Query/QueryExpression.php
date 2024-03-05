<?php

namespace Sanovskiy\SimpleObject\Query;

class QueryExpression implements \Stringable
{

    public function __construct(private readonly string $expression)
    {
    }

    public function __toString(): string
    {
        return $this->expression;
    }


}