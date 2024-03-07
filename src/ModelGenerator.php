<?php

namespace Sanovskiy\SimpleObject;

use Sanovskiy\SimpleObject\DatabaseParser\Schemas\TableSchema;

class ModelGenerator
{

    /**
     * @param TableSchema $tableSchema
     * @param array $connectionInfo
     * @return bool
     */
    public static function createModels(TableSchema $tableSchema, array $connectionInfo): bool
    {


        return true;
    }

}