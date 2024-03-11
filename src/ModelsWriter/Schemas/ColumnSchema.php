<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Schemas;

use DateTime;
use Sanovskiy\SimpleObject\DataTransformers\BooleanTransformer;
use Sanovskiy\SimpleObject\DataTransformers\DateTimeTransformer;
use Sanovskiy\SimpleObject\DataTransformers\EnumTransformer;
use Sanovskiy\SimpleObject\DataTransformers\JsonTransformer;

class ColumnSchema
{

    public readonly string $data_type;
    public readonly array $enumValues;

    public function __construct(
        public readonly string  $name,
        protected string        $original_data_type,
        public readonly bool    $nullable,
        public readonly ?string $default_value,
        public readonly bool    $primary_key,
        public readonly ?string $foreign_key,
        public readonly ?array  $references,
        public readonly bool    $unique
    )
    {
        // Определение массива возможных значений ENUM для MySQL
        if (str_contains(strtolower($original_data_type), 'enum')) {
            $this->data_type = 'enum';
            preg_match_all("/'([^']+)'/", $original_data_type, $matches);
            if (isset($matches[1])) {
                $this->enumValues = $matches[1];
            } else {
                $this->enumValues = [];
            }
        } else {
            $this->data_type = $this->original_data_type;
            $this->enumValues = [];
        }
    }

    public function getDefaultTransformations(): array
    {
        return match ($this->data_type) {
            'date' => [
                'propertyType' => DateTime::class,
                'transformerClass' => DateTimeTransformer::class,
                'transformerParams' => ['format' => 'Y-m-d']
            ],
            'timestamp', 'timestamp without time zone', 'datetime' => [
                'propertyType' => DateTime::class,
                'transformerClass' => DateTimeTransformer::class,
                'transformerParams' => ['format' => 'Y-m-d H:i:s']
            ],
            'tinyint', 'bit' => [
                'propertyType' => 'boolean',
                'transformerClass' => BooleanTransformer::class
            ],
            'int' => [
                'propertyType' => 'integer',
            ],
            'json', 'jsonb' => [
                'propertyType' => 'array',
                'transformerClass' => JsonTransformer::class
            ],
            'enum' => [
                'propertyType' => 'string',
                'transformerClass' => EnumTransformer::class,
                'transformerParams' => ['allowed_values' => $this->enumValues]
            ],
            default => [
                'propertyType' => 'string',
                'transformerClass' => null
            ],
        };
    }
}