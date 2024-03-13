<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use \InvalidArgumentException;

class UUIDTransformer extends DataTransformerAbstract
{
    protected static string $pattern = '/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i';

    public static function toProperty($value, $format = null): string
    {
        if (!self::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Unsupported value for ' . __METHOD__);
        }
        return $value;
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
        return is_string($value) && preg_match(static::$pattern, $value);
    }

    public static function isValidPropertyData($value): bool
    {
        return self::isValidDatabaseData($value);
    }
}