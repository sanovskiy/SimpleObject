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

    public static function toProperty($value, ?array $params = null)
    {
        if (!static::isValidDatabaseData($value,$params)) {
            throw new InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public static function toDatabaseValue($value, ?array $params = null)
    {
        if (!static::isValidPropertyData($value,$params)) {
            throw new InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public static function isValidDatabaseData($value, ?array $params = null): bool
    {
        return in_array($value, $params['allowed_values']);
    }

    public static function isValidPropertyData($value, ?array $params = null): bool
    {
        return in_array($value, $params['allowed_values'], true);
    }
}