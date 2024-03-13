<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;
use RuntimeException;

class BooleanTransformer extends DataTransformerAbstract
{

    public static function toProperty($value, $format = null): bool
    {
        if (in_array($value, ['1',1,'true','Y'],true)){
            return true;
        }
        if (in_array($value, ['0',0,'false','N'],true)){
            return false;
        }
        throw new InvalidArgumentException('Unsupported value for '.__METHOD__);
    }

    public static function toDatabaseValue($value, $format = null): string
    {
        return match (static::$databaseDriver) {
            'mysql' => $value ? '1' : '0',
            'pgsql', 'mssql' => $value ? 't' : 'f',
            default => throw new RuntimeException('Unsupported driver'),
        };
    }

    public static function isValidDatabaseData($value): bool
    {
        return in_array($value,['1',1,'true','Y','0',0,'false','N'],true);
    }

    public static function isValidPropertyData($value): bool
    {
        return is_bool($value);
    }
}