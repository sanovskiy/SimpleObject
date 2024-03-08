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
    public function toProperty($value, $format=null): DateTime
    {
        return is_numeric($value) ? new DateTime("@$value") : new DateTime($value);
    }

    public function toDatabaseValue($value, $format=null): ?string
    {
        $format = $format??'Y-m-d H:i:s';
        return $value->format($format);
    }

    public function isValidDatabaseData($value): bool
    {
        if (is_numeric($value)){
            return true;
        }

        return is_numeric($value) || (is_string($value) && strtotime($value) !== false);
    }

    public function isValidPropertyData($value): bool
    {
        return $value instanceof DateTime;
    }
}


