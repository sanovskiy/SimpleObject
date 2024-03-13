<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

class EnumTransformer extends DataTransformerAbstract
{
    protected static array $validValues = [];

    /**
     * @param array $validValues
     */
    public static function setValidValues(array $validValues): void
    {
        self::$validValues = $validValues;
    }

    public static function toProperty($value, $format = null)
    {
        if (!static::isValidDatabaseData($value)) {
            throw new InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public static function toDatabaseValue($value, $format = null)
    {
        if (!static::isValidPropertyData($value)) {
            throw new InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public static function isValidDatabaseData($value): bool
    {
        return in_array($value, static::$validValues);
    }

    public static function isValidPropertyData($value): bool
    {
        return in_array($value, static::$validValues, true);
    }
}