<?php

namespace Sanovskiy\SimpleObject\Collections;


use PDOStatement;

class QueryResult extends ImmutableCollection
{
    protected ?array $filters;
    protected ?string $sqlQuery;
    protected ?PDOStatement $pdoStatement;

    public function __construct(array $data, array $filters = [], string $sqlQuery = '', PDOStatement $pdoStatement=null, ?string $forceClass = null)
    {
        parent::__construct($data);
        $this->filters = $filters;
        $this->sqlQuery = $sqlQuery;
        $this->pdoStatement = $pdoStatement;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function getSqlQuery(): ?string
    {
        return $this->sqlQuery;
    }

    public function getPdoStatement(): ?PDOStatement
    {
        return $this->pdoStatement;
    }
}