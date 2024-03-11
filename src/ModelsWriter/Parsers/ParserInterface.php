<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Parsers;

use Sanovskiy\SimpleObject\ModelsWriter\Schemas\ColumnSchema;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

interface ParserInterface
{
    /**
     * @return TableSchema[]
     */
    public function getDatabaseTables(): array;

    /**
     * @param string $tableName
     * @return ColumnSchema[]
     */
    public function getTableColumns(string $tableName): array;
}