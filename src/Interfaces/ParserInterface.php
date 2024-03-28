<?php

namespace Sanovskiy\SimpleObject\Interfaces;

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

    /**
     * @param string $tableName
     * @return string|null
     */
    public function getPK(string $tableName): ?string;

    public function isTableExist(string $tableName): bool;
}