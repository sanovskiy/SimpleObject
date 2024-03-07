<?php

namespace Sanovskiy\SimpleObject\DatabaseParser;

interface ParserInterface
{
    public function getDatabaseTables(): array;
    public function getTableColumns(string $tableName): array;
}