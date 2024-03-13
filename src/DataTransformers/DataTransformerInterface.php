<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

interface DataTransformerInterface
{
    public static function toProperty($value, $format = null);

    public static function toDatabaseValue($value, $format = null);

    public static function isValidDatabaseData($value);

    public static function isValidPropertyData($value);

}