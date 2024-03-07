<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use DateTime;
use Exception;

class DateTimeTransformer implements DataTransformerInterface
{

    /**
     * @throws Exception
     */
    public function toProperty($value, $format=null): DateTime
    {
        return new DateTime($value);
    }

    public function toDatabaseValue($value,  $format=null): ?string
    {
        if (!$value instanceof DateTime){
            return null;
        }
        return $value->format($format);
    }
}



