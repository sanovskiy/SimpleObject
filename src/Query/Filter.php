<?php

namespace Sanovskiy\SimpleObject\Query;

use InvalidArgumentException;
use Project\Models\One\Base\Acl\Person;
use Sanovskiy\SimpleObject\ActiveRecordAbstract;

class Filter
{
    protected ?string $sql = null;
    protected ?array $bind = null;
    private ?array $tableFields=null;


    public function __construct(protected ActiveRecordAbstract|string $modelClass, protected array $filters)
    {
        if (!is_subclass_of($modelClass, ActiveRecordAbstract::class)) {
            throw new InvalidArgumentException('Model class must be a subclass of ActiveRecordAbstract');
        }
        if (is_object($this->modelClass)){
            $this->modelClass = get_class($this->modelClass);
        }
        $this->buildQuery();
    }

    protected function substituteColumnName(string $key): string
    {
        if ($this->modelClass::isPropertyExist($key)){
            return $key;
        }
        if ($this->modelClass::isPropertyExist($key)){
            return $this->modelClass::getPropertyField($key);
        }
        return $key;
    }

    public function getSQL(): string
    {
        return str_replace('{%fields}','`'.implode('`, `', $this->tableFields).'`',$this->sql);
    }

    public function getCountSQL(): string
    {
        return str_replace('{%fields}','count(*)',$this->sql);
    }

    public function getBind(): array
    {
        return $this->bind;
    }

    private function buildQuery(): void
    {
        $tableName = $this->modelClass::getTableName();
        $this->tableFields = $this->modelClass::getTableFields();


        $this->sql = 'SELECT {%fields} FROM `' . $tableName . '` WHERE ';
        $result = self::buildFilters($this->filters);
        $this->bind = $result['bind'];
        $this->sql .= $result['sql'];

    }

    protected function isMixedKeysArray($arr): bool
    {
        if (!is_array($arr)) {
            return false;
        }

        $hasNonIntKeys = false;
        $hasIntKeys = false;
        foreach ($arr as $key => $value) {
            $hasIntKeys = is_integer($key);
            $hasNonIntKeys = is_string($key);
        }

        return $hasIntKeys && $hasNonIntKeys;
    }

    const FILTER_TYPE_UNKNOWN = 0b0; // Unknown type
    const FILTER_TYPE_SCALAR = 0b1; // key is not numeric (table column name or SQL expression) and value is scalar
    const FILTER_TYPE_COMPARE_SHORT = 0b10; // key is not numeric and there are two elements in value
    const FILTER_TYPE_COMPARE_LONG = 0b100; // key is numeric and there are three elements in value, and first one is table column name or SQL expression and second is comparison operator
    const FILTER_TYPE_SUB_FILTER = 0b1000; // key is numeric or ':AND' or ':OR'
    const FILTER_TYPE_EXPRESSION = 0b10000; // value is instance of QueryExpression

    public function getFilterTypeName($type): string
    {
        $types = [
            self::FILTER_TYPE_UNKNOWN => 'Unknown',
            self::FILTER_TYPE_SCALAR => 'Scalar',
            self::FILTER_TYPE_COMPARE_SHORT => 'CompareShort',
            self::FILTER_TYPE_COMPARE_LONG => 'CompareLong',
            self::FILTER_TYPE_SUB_FILTER => 'SubFilter',
            self::FILTER_TYPE_EXPRESSION => 'Expression',
        ];
        return array_key_exists($type, $types) ? $types[$type] : 'ERROR: Unsupported type';
    }

    protected function detectFilterType($key, $value): int
    {
        return match (true) {
            ($value instanceof QueryExpression) => self::FILTER_TYPE_EXPRESSION,
            (!is_numeric($key) && is_scalar($value) && $this->modelClass::isTableFieldExist($key)) => self::FILTER_TYPE_SCALAR,
            (is_numeric($key) && !$this->isMixedKeysArray($value) && is_array($value) && count($value) === 3 && is_string($value[0]) && is_string($value[1])) => self::FILTER_TYPE_COMPARE_LONG,
            (!is_numeric($key) && !in_array($key,[':AND',':OR']) && is_array($value) && !$this->isMixedKeysArray($value) && count($value) === 2) => self::FILTER_TYPE_COMPARE_SHORT,
            ($key === ':AND' || $key === ':OR'), (is_numeric($key) && is_array($value) && !$this->isMixedKeysArray($value)) => self::FILTER_TYPE_SUB_FILTER,
            default => self::FILTER_TYPE_UNKNOWN
        };
    }

    public function buildFilters(array $filters): array
    {
        $sql = '';
        $bind = [];

        foreach ($filters as $key => $value) {
            $key = $this->substituteColumnName($key);
            $filterType = $this->detectFilterType($key, $value);

            switch ($filterType) {
                case self::FILTER_TYPE_SUB_FILTER:
                    // Handle AND/OR group
                    $groupType = substr($key, 1);
                    $subFilters = $this->buildFilters($value);
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
                    $sql .= '(' . $subSql . ')';
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
