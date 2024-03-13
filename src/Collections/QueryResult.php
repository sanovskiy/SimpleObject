<?php

namespace Sanovskiy\SimpleObject\Collections;


use PDOStatement;
use Sanovskiy\SimpleObject\Query\Filter;

class QueryResult extends ImmutableCollection
{
    protected Filter $filters;
    protected ?PDOStatement $pdoStatement;

    public function __construct(array $data, Filter $filters, PDOStatement $pdoStatement=null, ?string $forceClass = null)
    {
        parent::__construct($data);
        $this->filters = $filters;
        $this->pdoStatement = $pdoStatement;
    }

    public function getFilters(): Filter
    {
        return $this->filters;
    }

    public function getPdoStatement(): ?PDOStatement
    {
        return $this->pdoStatement;
    }
}