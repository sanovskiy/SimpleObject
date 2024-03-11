<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

class JsonTransformer extends DataTransformerAbstract
{

    public function toProperty($value, $format = true)
    {
        return json_decode($value, $format);
    }

    public function toDatabaseValue($value, $format = null): string
    {
        return (string) json_encode($value);
    }

    public function isValidDatabaseData($value): bool
    {
        return json_decode($value) !== null && json_last_error() === JSON_ERROR_NONE;
    }

    public function isValidPropertyData($value): bool
    {
        return true;
    }
}