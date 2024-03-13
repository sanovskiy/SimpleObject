<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

interface DataTransformerInterface
{
    public static function toProperty($value, $params = null);

    public static function toDatabaseValue($value, $params = null);

    public static function isValidDatabaseData($value);

    public static function isValidPropertyData($value);

}