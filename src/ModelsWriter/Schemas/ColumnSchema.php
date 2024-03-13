<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Schemas;

use DateTime;
use Sanovskiy\SimpleObject\DataTransformers\BooleanTransformer;
use Sanovskiy\SimpleObject\DataTransformers\DateTimeTransformer;
use Sanovskiy\SimpleObject\DataTransformers\EnumTransformer;
use Sanovskiy\SimpleObject\DataTransformers\JsonTransformer;
use Sanovskiy\SimpleObject\DataTransformers\FloatTransformer;
use Sanovskiy\SimpleObject\DataTransformers\IntegerTransformer;
use Sanovskiy\SimpleObject\DataTransformers\UUIDTransformer;

class ColumnSchema
{

    public readonly string $data_type;
    public readonly array $enumValues;
    public readonly ?int $limit;

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
            $this->limit = null;
        } else {
            preg_match("/([a-zA-Z]+)(?:\((\d+)\))?/", $original_data_type, $matches);
            $this->data_type = $matches[1];
            $this->limit = isset($matches[2]) ? (int) $matches[2] : null;
            $this->enumValues = [];
        }
    }

    public function getPHPType(): string
    {
        return match (strtolower($this->data_type)) {
            'tinyint', 'bool', 'boolean', 'bit' => 'bool',
            'float', 'double', 'decimal', 'real', 'double precision', 'numeric' => 'float',
            'smallint', 'mediumint', 'int', 'bigint', 'integer', 'year' => 'int',
            'timestamp', 'time', 'date', 'datetime' => DateTime::class,
            'varchar', 'enum', 'text', 'char', 'longtext', 'mediumtext', 'binary', 'varbinary', 'blob', 'longblob', 'mediumblob', 'set',
            'tinyblob', 'tinytext', 'uuid', 'point', 'linestring', 'polygon', 'geometry', 'multipoint', 'multilinestring', 'multipolygon',
            'geometrycollection' => 'string',
            'json' => 'array',
            default => 'mixed'
        };
    }

    public function getDefaultTransformation(): array
    {
        return match (strtolower($this->data_type)) {
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
            'time' => [
                'propertyType' => DateTime::class,
                'transformerClass' => DateTimeTransformer::class,
                'transformerParams' => ['format' => 'H:i:s']
            ],
            'tinyint', 'bit' => [
                'propertyType' => 'boolean',
                'transformerClass' => BooleanTransformer::class
            ],
            'int', 'smallint', 'mediumint', 'bigint', 'integer', 'year' => [
                'propertyType' => 'integer',
                'transformerClass' => IntegerTransformer::class
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
            'float', 'double', 'decimal', 'real', 'double precision', 'numeric' => [
                'propertyType' => 'float',
                'transformerClass' => FloatTransformer::class,
            ],
            'uuid'=>[
                'propertyType' => 'string',
                'transformerClass' => UUIDTransformer::class
            ],
            'varchar', 'text', 'char', 'longtext', 'mediumtext', 'binary', 'varbinary', 'blob', 'longblob', 'mediumblob', 'set',
            'tinyblob', 'tinytext', 'point', 'linestring', 'polygon', 'geometry', 'multipoint', 'multilinestring', 'multipolygon',
            'geometrycollection' => [
                'propertyType' => 'string',
            ],
            default => [
                'propertyType' => 'mixed',
            ],
        };
    }

}