<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class IntegerTransformer extends DataTransformerAbstract
{
    public static function toProperty($value, ?array $params = null): ?int
    {
        if (is_null($value)){return null;}

        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }

        return (int) $value;
    }

    public static function toDatabaseValue($value, ?array $params = null): ?string
    {
        if (is_null($value)){return null;}

        if (!self::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return (string) $value;
    }

    public static function isValidDatabaseData($value, ?array $params = null): bool
    {
        return is_null($value) || is_numeric($value);
    }

    public static function isValidPropertyData($value, ?array $params = null): bool
    {
        return is_null($value) || is_int($value);
    }
}