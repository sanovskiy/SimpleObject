<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class FloatTransformer extends DataTransformerAbstract
{

    public static function toProperty($value, $params = null): ?float
    {
        if (is_null($value)){return null;}

        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }

        return (float) $value;
    }

    public static function toDatabaseValue($value, $params = null): ?string
    {
        if (is_null($value)){return null;}

        if (!self::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return (string) $value;
    }

    public static function isValidDatabaseData($value): bool
    {
        return is_null($value) || is_numeric($value);
    }

    public static function isValidPropertyData($value): bool
    {
        return is_null($value) || is_float($value);
    }
}