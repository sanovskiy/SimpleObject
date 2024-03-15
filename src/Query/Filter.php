<?php

namespace Sanovskiy\SimpleObject\Query;

use InvalidArgumentException;
use Project\Models\One\Base\Acl\Person;
use Sanovskiy\SimpleObject\ActiveRecordAbstract;
use Sanovskiy\SimpleObject\ConnectionManager;

class Filter
{
    protected ?string $sql = null;
    protected ?array $bind = null;
    private ?array $tableFields = null;


    public function __construct(protected array $filters, protected ActiveRecordAbstract|string|null $modelClass = null)
    {
        if (!is_null($this->modelClass)) {
            if (!is_subclass_of($modelClass, ActiveRecordAbstract::class)) {
                throw new InvalidArgumentException('Model class must be a subclass of ActiveRecordAbstract');
            }
            if (is_object($this->modelClass)) {
                $this->modelClass = get_class($this->modelClass);
            }
        }
        $this->buildQuery();
    }

    protected function substituteColumnName(string $key): string
    {
        if (!is_null($this->modelClass)) {
            if ($this->modelClass::isPropertyExist($key)) {
                return $key;
            }
            if ($this->modelClass::isPropertyExist($key)) {
                return $this->modelClass::getPropertyField($key);
            }
        }
        return $key;
    }

    public function getSQL(): string
    {
        if (is_null($this->tableFields)) {
            return $this->sql;
        }
        $config = $this->getPlaceholdersForCurrentDriver();
        // Replace {%fields} with appropriate syntax for the database
        $fields = array_map(function ($field) use ($config) {
            return $config['columnDelimiters']['left'] . $field . $config['columnDelimiters']['right'];
        }, $this->tableFields);

        return str_replace('{%fields}', '`' . implode('`, `', ($fields)) . '`', $this->sql);
    }

    public function getCountSQL(): string
    {
        return str_replace('{%fields}', 'count(*)', $this->sql);
    }

    public function getBind(): array
    {
        return $this->bind;
    }

    private function getPlaceholdersForCurrentDriver(): ?array
    {
        $placeholders = [
            'mysql' => ['placeholder' => '?', 'offsetPlaceholder' => 'OFFSET ?', 'groupBy' => 'GROUP BY', 'columnDelimiters' => ['left' => '`', 'right' => '`']],
            'pgsql' => ['placeholder' => '$', 'offsetPlaceholder' => 'OFFSET ?', 'groupBy' => 'GROUP BY', 'columnDelimiters' => ['left' => '"', 'right' => '"']],
            'mssql' => ['placeholder' => '?', 'offsetPlaceholder' => 'OFFSET ? ROWS FETCH NEXT ? ROWS ONLY', 'groupBy' => 'GROUP BY', 'columnDelimiters' => ['left' => '[', 'right' => ']']],
        ];
        if (is_null($this->modelClass)) {
            return $placeholders['mysql'];
        }
        return $placeholders[ConnectionManager::getConfig($this->modelClass::getSimpleObjectConfigNameRead())?->getDriver()] ?: null;

    }

    private function buildQuery(): void
    {
        $tableName = (is_null($this->modelClass) ? '%table_name%' : $this->modelClass::getTableName());
        if (!is_null($this->modelClass)) {
            $this->tableFields = $this->modelClass::getTableFields();
        }
        // Determine SQL syntax based on the database driver
        $config = $this->getPlaceholdersForCurrentDriver();
        if (empty($config)) {
            throw new \RuntimeException('Unsupported database driver');
        }
        $placeholder = $config['placeholder'];
        $offsetPlaceholder = $config['offsetPlaceholder'];
        $groupBy = $config['groupBy'];

        // Build SELECT statement
        $this->sql = 'SELECT {%fields} FROM ' . $tableName;

        $result = self::buildWhere($this->filters);

        $this->bind = $result['bind'];
        if (!empty($result['bind'])) {
            $this->sql .= ' WHERE ' . $result['sql'];
        }


        // Add additional instructions
        $instructions = [':ORDER', ':LIMIT', ':GROUP'];
        foreach ($instructions as $instruction) {
            if (isset($this->filters[$instruction])) {
                switch ($instruction) {
                    case ':ORDER':
                        $this->sql .= ' ORDER BY ' . $this->filters[$instruction][0] . ' ' . strtoupper($this->filters[$instruction][1]);
                        break;
                    case ':LIMIT':
                        $this->sql .= ' LIMIT ' . $placeholder;
                        $this->bind[] = $this->filters[$instruction][0];
                        if (count($this->filters[$instruction]) === 2) {
                            $this->sql .= ' ' . $offsetPlaceholder;
                            $this->bind[] = $this->filters[$instruction][1];
                        }
                        break;
                    case ':GROUP':
                        $this->sql .= ' ' . $groupBy . ' ' . $this->filters[$instruction][0];
                        break;
                }
            }
        }

        //$this->sql = str_replace('{%fields}', implode(', ', $fields), $this->sql);
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

    protected function substituteOperator(string $operator): string
    {
        return match (trim($operator)) {
            'is', 'in' => '=',
            '!=', '!==', 'not', 'not in', 'is not' => '<>',
            default => $operator,
        };
    }

    public function buildWhere(array $filters, $joinBy = 'AND'): array
    {
        $sql = '';
        $bind = [];
        if (!in_array($joinBy, ['AND', 'OR'])) {
            $joinBy = 'AND';
        }
        $subWhereArray = [];
        foreach ($filters as $key => $value) {
            $key = $this->substituteColumnName($key);
            $filterType = FilterTypeDetector::detectFilterType($key, $value, $this->modelClass);
            //echo "filter type is " . FilterTypeDetector::getFilterTypeName($filterType);
            //var_dump([$key, $value]);
            switch ($filterType) {
                case FilterTypeDetector::FILTER_TYPE_QUERY_RULE:
                    // Ignore this. It will be used later in buildQuery().
                    break;
                case FilterTypeDetector::FILTER_TYPE_SUB_FILTER:
                    // Handle AND/OR group

                    preg_match('/:(AND|OR)(.*)/', $key, $matches);
                    if (count($matches) >= 3) {
                        $groupType = $matches[1];
                    } else {
                        $groupType = 'AND';
                    }
                    $subFilters = $this->buildWhere($value, $groupType);
                    if (!empty($subFilters['sql'])) {
                        if (!empty($sql)) {
                            $sql .= sprintf(" %s ", $joinBy);
                        }
                        $sql .= sprintf("(%s)", $subFilters['sql']);
                    }
                    $bind = array_merge($bind, $subFilters['bind']);
                    break;
                case FilterTypeDetector::FILTER_TYPE_COMPARE_SHORT:
                    $operator = $this->substituteOperator($value[0]);
                    $subSql = sprintf('%s %s ?', $key, $operator);
                    $bind[] = $value[1];
                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }
                    $sql .= '(' . $subSql . ')';
                    break;
                case FilterTypeDetector::FILTER_TYPE_COMPARE_LONG:
                    $operator = $this->substituteOperator($value[1]);
                    if (is_null($value[1])) {
                        $sql .= sprintf('%s IS' . ($operator == '<>' ? ' NOT' : '') . ' NULL', $value[0]);
                    } else {
                        $sql .= sprintf('%s %s ?', $value[0], $operator);
                        $bind[] = $value[2];
                    }
                    if (!empty($sql)) {
                        $sql .= sprintf(" %s ", $joinBy);
                    }
                    break;
                case FilterTypeDetector::FILTER_TYPE_SCALAR:
                    if (!empty($sql)) {
                        $sql .= sprintf(" %s ", $joinBy);
                    }
                    if (is_null($value)) {
                        $sql = sprintf('%s IS NULL', $value[0]);
                    } else {
                        $sql .= sprintf('%s = ?', $key);
                        $bind[] = $value;
                    }
                    break;
                case FilterTypeDetector::FILTER_TYPE_EXPRESSION:
                    if (!empty($sql)) {
                        $sql .= sprintf(" %s ", $joinBy);
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
