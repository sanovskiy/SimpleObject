<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use DateTime;
use Exception;
use InvalidArgumentException;

class DateTimeTransformer extends DataTransformerAbstract
{

    /**
     * @throws Exception
     */
    public static function toProperty($value, $params=null): DateTime
    {
        return is_numeric($value) ? new DateTime("@$value") : new DateTime($value);
    }

    public static function toDatabaseValue($value, $params=null): ?string
    {
        $date_format = $params['format']??'Y-m-d H:i:s';
        if (self::isValidDatabaseData($value)){
            return $value;
        }
        return $value->format($date_format);
    }

    public static function isValidDatabaseData($value): bool
    {
        if (is_numeric($value)){
            return true;
        }

        return is_numeric($value) || (is_string($value) && strtotime($value) !== false);
    }

    public static function isValidPropertyData($value): bool
    {
        return $value instanceof DateTime;
    }
}



