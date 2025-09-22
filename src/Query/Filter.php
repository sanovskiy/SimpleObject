<?php

namespace Sanovskiy\SimpleObject\Query;

use InvalidArgumentException;
use RuntimeException;
use Sanovskiy\SimpleObject\ActiveRecordAbstract;
use Sanovskiy\SimpleObject\ConnectionManager;

class Filter
{
    const ORDER = ':ORDER';
    const LIMIT = ':LIMIT';
    const GROUP = ':GROUP';
    const AND_SUBFILTER = ':AND';
    protected ?string $sql = null;
    protected ?string $sqlInstructions = '';
    protected ?array $bind = null;
    protected ?array $bindInstructions = null;
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

    public function getSQL(string $tableName = null): string
    {
        if (is_null($this->tableFields)) {
            if (empty($tableName)) {
                throw new InvalidArgumentException('Table name needed for call without model');
            }
            return str_replace('{%table_name}', $tableName, str_replace('{%fields}', '*', $this->sql));
        }
        $config = $this->getPlaceholdersForCurrentDriver();
        // Replace {%fields} with appropriate syntax for the database
        $fields = array_map(function ($field) use ($config) {
            return $config['columnDelimiters']['left'] . $field . $config['columnDelimiters']['right'];
        }, $this->tableFields);
        return str_replace('{%fields}', implode(', ', ($fields)), $this->sql).' '.$this->sqlInstructions;
    }

    public function getBind(): array
    {
        return array_merge($this->bind, $this->bindInstructions);
    }

    public function getCountSQL(): string
    {
        return str_replace('{%fields}', 'count(*)', $this->sql);
    }

    public function getCountBind(): array
    {
        return $this->bind;
    }

    const PH_VALUE = 'placeholder';
    const PH_LIMIT_INVERT = 'invertLimitOffsetOrder';
    const PH_LIMIT = 'limitPlaceholder';
    const PH_OFFSET = 'offsetPlaceholder';
    const PH_GROUP = 'groupBy';
    const PH_DELIMITERS = 'columnDelimiters';
    const PH_D_LEFT = 'left';
    const PH_D_RIGHT = 'right';

    private function getPlaceholdersForCurrentDriver(): ?array
    {
        $placeholders = [
            'mysql' => [
                self::PH_VALUE => '?',
                self::PH_LIMIT => 'LIMIT ?',
                self::PH_OFFSET => 'OFFSET ?',
                self::PH_LIMIT_INVERT => false,
                self::PH_GROUP => 'GROUP BY',
                self::PH_DELIMITERS => [self::PH_D_LEFT => '`', self::PH_D_RIGHT => '`']
            ],
            'pgsql' => [
                self::PH_VALUE => '$',
                self::PH_LIMIT => 'LIMIT ?',
                self::PH_OFFSET => 'OFFSET ?',
                self::PH_LIMIT_INVERT => false,
                self::PH_GROUP => 'GROUP BY',
                self::PH_DELIMITERS => [self::PH_D_LEFT => '"', self::PH_D_RIGHT => '"']
            ],
            'mssql' => [
                self::PH_VALUE => '?',
                self::PH_LIMIT => 'FETCH NEXT ? ROWS ONLY',
                self::PH_OFFSET => 'OFFSET ? ROWS',
                self::PH_LIMIT_INVERT => true,
                self::PH_GROUP => 'GROUP BY',
                self::PH_DELIMITERS => [self::PH_D_LEFT => '[', self::PH_D_RIGHT => ']']
            ],
        ];
        if (is_null($this->modelClass)) {
            return $placeholders['mysql'];
        }
        return $placeholders[ConnectionManager::getConfig($this->modelClass::getSimpleObjectConfigNameRead())?->getDriver()] ?: null;

    }

    private function buildQuery(): void
    {
        $tableName = (is_null($this->modelClass) ? '{%table_name}' : $this->modelClass::getTableName());
        if (!is_null($this->modelClass)) {
            $this->tableFields = $this->modelClass::getTableFields();
        }
        // Determine SQL syntax based on the database driver
        $config = $this->getPlaceholdersForCurrentDriver();
        if (empty($config)) {
            throw new RuntimeException('Unsupported database driver');
        }

        // Build SELECT statement
        $this->sql = 'SELECT {%fields} FROM ' . $tableName;

        $result = self::buildWhere($this->filters);

        $this->bind = $result['bind'];
        if (!empty($result['bind'])) {
            $this->sql .= ' WHERE ' . $result['sql'];
        }


        // Add additional instructions
        $instructions = [self::ORDER, self::LIMIT, self::GROUP];
        foreach ($instructions as $instruction) {
            if (isset($this->filters[$instruction])) {
                switch ($instruction) {
                    case self::ORDER:
                        if (!is_array($this->filters[$instruction])){
                            $parts = explode(' ',$this->filters[$instruction]);
                            $column = $parts[0];
                            $dir = $parts[1]??'ASC';
                            $this->filters[$instruction] = [$column,$dir];
                        }
                        $this->sqlInstructions .= ' ORDER BY ' . $config[self::PH_DELIMITERS][self::PH_D_LEFT] . $this->filters[$instruction][0] . $config[self::PH_DELIMITERS][self::PH_D_RIGHT] . ' ' . strtoupper($this->filters[$instruction][1] ?? 'asc');
                        break;
                    case self::LIMIT:
                        if (!is_array($this->filters[$instruction])){
                            $this->filters[$instruction] = [$this->filters[$instruction],0];
                        }
                        $bind = [$this->filters[$instruction][0]];
                        $limitSQL = $config[self::PH_LIMIT];
                        $offsetSQL = '';
                        if (count($this->filters[$instruction]) > 1) {
                            $offsetSQL .= ' ' . $config[self::PH_OFFSET];
                            $bind[] = $this->filters[$instruction][1];
                        }
                        $this->sqlInstructions .= ' ' . ($config[self::PH_LIMIT_INVERT] ? ($offsetSQL . ' ' . $limitSQL) : ($limitSQL . ' ' . $offsetSQL));
                        $this->bindInstructions = array_merge($this->bindInstructions, $config[self::PH_LIMIT_INVERT] ? array_reverse($bind) : $bind);
                        break;
                    case self::GROUP:
                        $this->sqlInstructions .= ' ' . $config[self::PH_GROUP] . ' ' . $config[self::PH_DELIMITERS][self::PH_D_LEFT] . $this->filters[$instruction][0] . $config[self::PH_DELIMITERS][self::PH_D_RIGHT];
                        break;
                }
            }
        }
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
                        $subWhereArray[] = sprintf("(%s)", $subFilters['sql']);
                    }
                    $bind = array_merge($bind, $subFilters['bind']);
                    break;
                // Short comparison
                case FilterTypeDetector::FILTER_TYPE_COMPARE_SHORT:
                    $operator = $this->substituteOperator($value[0]);

                    // BETWEEN
                    if (strtolower($value[0]) === 'between' && count($value) === 3) {
                        $subWhereArray[] = sprintf('%s BETWEEN ? AND ?', $key);
                        $bind[] = $value[1];
                        $bind[] = $value[2];
                    }
                    // NULL
                    elseif (is_null($value[1])) {
                        $subWhereArray[] = sprintf('%s IS' . ($operator == '<>' ? ' NOT' : '') . ' NULL', $key);
                    }
                    // IN for array
                    elseif ($operator === '=' && is_array($value[1]) && FilterTypeDetector::isIndexedArray($value[1])) {
                        $subWhereArray[] = sprintf('%s IN(' . implode(',', array_fill(0, count($value[1]), '?')) . ')', $key);
                        $bind = array_merge($bind, $value[1]);
                    }
                    // Default
                    else {
                        $subWhereArray[] = sprintf('%s %s ?', $key, $operator);
                        $bind[] = $value[1];
                    }
                    break;
                // Long comparison
                case FilterTypeDetector::FILTER_TYPE_COMPARE_LONG:
                    $key = $value[0];
                    $operator = $this->substituteOperator($value[1]);

                    // BETWEEN
                    if (strtolower($value[1]) === 'between' && count($value) === 4) {
                        $subWhereArray[] = sprintf('%s BETWEEN ? AND ?', $key);
                        $bind[] = $value[2]; // начальное значение
                        $bind[] = $value[3]; // конечное значение
                    }
                    // NULL
                    elseif (is_null($value[2])) {
                        $subWhereArray[] = sprintf('%s IS' . ($operator == '<>' ? ' NOT' : '') . ' NULL', $key);
                    }
                    // IN for array
                    elseif (is_array($value[2]) && in_array($operator, ['=', '<>'])) {
                        $subWhereArray[] = sprintf('%s ' . ($operator == '<>' ? 'NOT ' : '') . 'IN(' . implode(',', array_fill(0, count($value[2]), '?')) . ')', $key);
                        $bind = array_merge($bind, $value[2]);
                    }
                    // Default
                    else {
                        $subWhereArray[] = sprintf('%s %s ?', $value[0], $operator);
                        $bind[] = $value[2];
                    }
                    break;
                case FilterTypeDetector::FILTER_TYPE_SCALAR:
                    if (is_null($value)) {
                        $subWhereArray[] = sprintf('%s IS NULL', $key);
                    } elseif (is_array($value) && FilterTypeDetector::isIndexedArray($value)) {
                        $subWhereArray[] = sprintf('%s IN(' . implode(',', array_fill(0, count($value), '?')) . ')', $key);
                        $bind = array_merge($bind, $value);
                    } else {
                        $subWhereArray[] = sprintf('%s = ?', $key);
                        $bind[] = $value;
                    }
                    break;
                case FilterTypeDetector::FILTER_TYPE_EXPRESSION:
                    /** @var QueryExpression $value */
                    if (is_numeric($key)) {
                        $subWhereArray[] = sprintf('(%s)', $value->getExpression());
                    } else {
                        $subWhereArray[] = sprintf('(%s %s)', $key, $value->getExpression());
                    }
                    $bind = array_merge($bind, $value->getBind());
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported filter type for key: $key");
            }
        }
        $sql = implode(' ' . $joinBy . ' ', $subWhereArray);
        return compact('sql', 'bind');
    }
}
