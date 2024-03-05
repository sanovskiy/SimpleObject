<?php

namespace Sanovskiy\SimpleObject\Query;

use InvalidArgumentException;
use Sanovskiy\SimpleObject\ActiveRecordAbstract;

class Filter
{
    protected ?string $sql = null;
    protected ?array $bind = null;

    public function __construct(protected string $modelClass, protected array $filters)
    {
        if (!is_subclass_of($modelClass, ActiveRecordAbstract::class)) {
            throw new InvalidArgumentException('Model class must be a subclass of ActiveRecordAbstract');
        }
        $this->buildQuery();
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    public function getBind(): array
    {
        return $this->bind;
    }

    private function buildQuery(): void
    {
        $tableName = call_user_func([$this->modelClass, 'getTableName']);
        $tableFields = call_user_func([$this->modelClass, 'getTableFields']);

        $this->sql = 'SELECT `' . (implode('`, `', $tableFields)) . '` FROM `' . $tableName . '` WHERE ';
        $result = self::buildFilters($this->filters);
        $this->bind = $result['bind'];
        $this->sql .= $result['sql'];

    }

    public static function buildFilter(array $filters): array
    {
        $f = [
            ['parent_id', 'in', [1, 2, 3, 4, 5]],
            ['parent_id', 'not in', [6, 7, 8, 9]],
            'is_local' => true,
            ':AND' => [
                ['date_created', '>', '2023-01-01'],
                ['date_created', '<', '2024-01-01'],
            ],
            ':OR' => [
                'main_record' => 112,
                'type' => 'employee',
            ],
            'nested_level' => 15
        ];

        $sql = '';
        $bind = [];
        foreach ($filters as $key => $value) {

        }
    }

    const FILTER_TYPE_UNKNOWN = 0b0; // Unknown type
    const FILTER_TYPE_SCALAR = 0b1; // key is not numeric (table column name or SQL expression) and value is scalar
    const FILTER_TYPE_COMPARE_SHORT = 0b10; // key is not numeric and there are two elements in value
    const FILTER_TYPE_COMPARE_LONG = 0b100; // key is numeric and there are three elements in value, and first one is table column name or SQL expression and second is comparison operator
    const FILTER_TYPE_SUB_FILTER = 0b1000; // key is numeric or ':AND' or ':OR'
    const FILTER_TYPE_EXPRESSION = 0b10000; // value is instance of QueryExpression
    protected static function detectFilterType($key, $value): int
    {
        return match (true){
            ($value instanceof QueryExpression)=>self::FILTER_TYPE_EXPRESSION,
            (!is_numeric($key) && is_scalar($value))=>self::FILTER_TYPE_SCALAR,
            (is_array($value) && count($value) === 2)=>self::FILTER_TYPE_COMPARE_SHORT,
            (is_numeric($key) && is_array($value) && count($value) === 3 && is_string($value[0]) && is_string($value[1]))=>self::FILTER_TYPE_COMPARE_LONG,
            (is_numeric($key) || $key === ':AND' || $key === ':OR') => self::FILTER_TYPE_SUB_FILTER,
            default => self::FILTER_TYPE_UNKNOWN
        };
    }

    public static function buildFilters(array $filters): array
    {
        $sql = '';
        $bind = [];

        foreach ($filters as $key => $value) {
            $filterType = self::detectFilterType($key, $value);

            switch ($filterType) {
                case self::FILTER_TYPE_SUB_FILTER:
                    // Handle AND/OR group
                    $groupType = substr($key, 1);
                    $subFilters = self::buildFilters($value);
                    if (!empty($subFilters['sql'])) {
                        if (!empty($sql)) {
                            $sql .= sprintf(" %s ", $groupType);
                        }
                        $sql .= sprintf("(%s)", $subFilters['sql']);
                    }
                    $bind = array_merge($bind, $subFilters['bind']);
                    break;
                case self::FILTER_TYPE_COMPARE_SHORT:
                    $subSql = sprintf('%s %s ?', $key, $value[0]);
                    $bind[] = $value[1];
                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }
                    $sql .= "($subSql)";
                    break;
                case self::FILTER_TYPE_COMPARE_LONG:
                    $subSql = sprintf('%s %s ?', $value[0], $value[1]);
                    $bind[] = $value[2];
                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }
                    $sql .= "($subSql)";
                    break;
                case self::FILTER_TYPE_SCALAR:
                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }
                    $sql .= sprintf('%s = ?', $key);
                    $bind[] = $value;
                    break;
                case self::FILTER_TYPE_EXPRESSION:
                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }
                    $sql .= sprintf('%s %s', $key, $value->getExpression());
                    $bind = array_merge($bind, $value->getBindValues());
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported filter type for key: $key");
            }
        }

        return compact('sql', 'bind');
    }
}