<?php

namespace Sanovskiy\SimpleObject\Interfaces;

interface DataTransformerInterface
{
    public static function toProperty($value, ?array $params = null);

    public static function toDatabaseValue($value, ?array $params = null);

    public static function isValidDatabaseData($value, ?array $params = null);

    public static function isValidPropertyData($value, ?array $params = null);

}