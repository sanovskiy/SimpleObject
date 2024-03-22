<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class JsonTransformer extends DataTransformerAbstract
{

    public static function toProperty($value, $params = true)
    {
        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return json_decode($value, $params);
    }

    public static function toDatabaseValue($value, $params = null): string
    {
        if (!self::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return (string)json_encode($value);
    }

    public static function isValidDatabaseData($value): bool
    {
        return is_null($value) || (json_decode($value) !== null && json_last_error() === JSON_ERROR_NONE);
    }

    public static function isValidPropertyData($value): bool
    {
        return true;
    }
}