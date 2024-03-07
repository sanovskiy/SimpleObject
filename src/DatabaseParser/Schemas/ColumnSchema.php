<?php

namespace Sanovskiy\SimpleObject\DatabaseParser\Schemas;

class ColumnSchema
{

    public function __construct(
        public readonly string $name,
        public readonly string $data_type,
        public readonly bool $nullable,
        public readonly ?string $default_value,
        public readonly bool $primary_key,
        public readonly ?string $foreign_key,
        public readonly ?array $references,
        public readonly bool $unique
    )
    {
    }

    public function getDefaultTransformations(): array
    {
        switch ($this->data_type) {
            case 'date':
                $rules = [
                    'read' => function($value){},//['date2time' => []],
                    'write' => ['time2date' => ['format' => 'Y-m-d']],
                ];
                $propertyType = \DateTime::class;
                break;
            case 'timestamp':
            case 'timestamp without time zone':
            case 'datetime':
                $dataTransformRules[$colName] = [
                    'read' => ['date2time' => []],
                    'write' => ['time2date' => ['format' => 'Y-m-d H:i:s']],
                ];
                $colVal[$colName] = 'integer';
                break;
            case 'tinyint':
            case 'bit':
                $dataTransformRules[$colName] = [
                    'read' => ['digit2boolean' => []],
                    'write' => ['boolean2digit' => []],
                ];
                $colVal[$colName] = 'boolean';
                break;
            case 'int':
                $colVal[$colName] = 'integer';
                break;
            case 'json':
            case 'jsonb':
                $dataTransformRules[$colName] = [
                    'read' => ['unjsonize' => []],
                    'write' => ['jsonize' => []],
                ];
                $colVal[$colName] = 'array';
                break;
            case 'enum':
            default:
                $colVal[$colName] = 'string';
                break;
        }
    }
}