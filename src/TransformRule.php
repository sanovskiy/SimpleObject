<?php namespace Sanovskiy\SimpleObject;

use Sanovskiy\SimpleObject\Interfaces\DataTransformerInterface;

class TransformRule
{
    /**
     * @var class-string<DataTransformerInterface>
     */
    private string $transformerClass;
    private ?array $transformerParams=null;
    private ?string $propertyType;

    public function __construct(string $transformerClass, ?array $transformerParams = null, string $propertyType=null)
    {
        if (!in_array(DataTransformerInterface::class, class_implements($transformerClass))) {
            throw new \InvalidArgumentException('The transformer class must implement the DataTransformerInterface.');
        }
        $this->transformerClass = $transformerClass;
        $this->transformerParams = $transformerParams;
        $this->propertyType = $propertyType;
    }

    public function toProperty(mixed $value): mixed
    {
        return $this->transformerClass::toProperty($value, $this->transformerParams);
    }

    public function toDatabaseValue(mixed $value): mixed
    {
        return $this->transformerClass::toDatabaseValue($value, $this->transformerParams);
    }

    public function isValidPropertyData(mixed $value): bool
    {
        return $this->transformerClass::isValidPropertyData($value);
    }

    public function isValidDatabaseData(mixed $value): bool
    {
        return $this->transformerClass::isValidDatabaseData($value);
    }

    /**
     * @return class-string<DataTransformerInterface>
     */
    public function getTransformerClass(): string
    {
        return $this->transformerClass;
    }

    public function getTransformerParams(): ?array
    {
        return $this->transformerParams;
    }

    public function getPropertyType(): string
    {
        return $this->propertyType;
    }

}