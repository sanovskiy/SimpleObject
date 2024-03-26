<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Schemas;

use Sanovskiy\SimpleObject\Interfaces\ParserInterface;
use Sanovskiy\Utility\NamingStyle;

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

    public function getModelName():string
    {
        return NamingStyle::toCamelCase($this->tableName, capitalizeFirstCharacter: true);
    }
}