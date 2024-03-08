<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

interface DataTransformerInterface
{
    public function toProperty($value, $format = null);

    public function toDatabaseValue($value, $format = null);

    public function isValidDatabaseData($value);

    public function isValidPropertyData($value);

}