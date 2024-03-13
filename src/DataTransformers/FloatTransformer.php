<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class FloatTransformer extends DataTransformerAbstract
{

    public static function toProperty($value, $format = null): float
    {
        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }

        return (float) $value;
    }

    public static function toDatabaseValue($value, $format = null): string
    {
        if (!self::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return (string) $value;
    }

    public static function isValidDatabaseData($value): bool
    {
        return is_numeric($value);
    }

    public static function isValidPropertyData($value): bool
    {
        return is_float($value);
    }
}