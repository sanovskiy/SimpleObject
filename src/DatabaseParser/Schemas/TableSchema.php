<?php

namespace Sanovskiy\SimpleObject\DatabaseParser\Schemas;

use Sanovskiy\SimpleObject\DatabaseParser\ParserInterface;

class TableSchema
{
    public function __construct(public readonly string $tableName, protected readonly ParserInterface $parser)
    {
    }

    /**
     * @return ColumnSchema[]
     */
    public function getColumns(): array
    {
        return $this->parser->getTableColumns($this->tableName);
    }
}