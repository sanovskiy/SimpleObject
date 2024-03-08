<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

class EnumTransformer extends DataTransformerAbstract
{

    public function __construct(string $databaseDriver, protected array $validValues)
    {
        parent::__construct($databaseDriver);
    }

    public function toProperty($value, $format = null)
    {
        if (!$this->isValidDatabaseData($value)) {
            throw new \InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public function toDatabaseValue($value, $format = null)
    {
        if (!$this->isValidPropertyData($value)) {
            throw new \InvalidArgumentException('Invalid data for ' . __METHOD__);
        }

        return $value;
    }

    public function isValidDatabaseData($value): bool
    {
        return in_array($value, $this->validValues);
    }

    public function isValidPropertyData($value): bool
    {
        return in_array($value, $this->validValues, true);
    }
}