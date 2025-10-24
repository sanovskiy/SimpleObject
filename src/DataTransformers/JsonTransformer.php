<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class JsonTransformer extends DataTransformerAbstract
{

    public static function toProperty($value, $params = [])
    {
        if (is_null($value)){return null;}

        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return json_decode($value, $params['assoc']??true);
    }

    public static function toDatabaseValue($value, $params = null): ?string
    {
        if (is_null($value)){return null;}

        if (!self::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return (string)json_encode($value);
    }

    public static function isValidDatabaseData($value, ?array $params = null): bool
    {
        return is_null($value) || (json_decode($value) !== null && json_last_error() === JSON_ERROR_NONE);
    }

    public static function isValidPropertyData($value, ?array $params = null): bool
    {
        return true;
    }
}