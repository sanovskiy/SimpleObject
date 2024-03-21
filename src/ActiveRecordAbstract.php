<?php

namespace Sanovskiy\SimpleObject;

use ArrayAccess;
use Countable;
use Error;
use Exception;
use InvalidArgumentException;
use Iterator;
use PDO;
use PDOStatement;
use RuntimeException;
use Sanovskiy\SimpleObject\Collections\QueryResult;
use Sanovskiy\SimpleObject\DataTransformers\DataTransformerInterface;
use Sanovskiy\SimpleObject\Query\Filter;
use Sanovskiy\Utility\NamingStyle;

/**
 * @property int $Id Identity
 */
class ActiveRecordAbstract implements Iterator, ArrayAccess, Countable
{
    protected static string $SimpleObjectConfigNameRead = 'default';
    protected static string $SimpleObjectConfigNameWrite = 'default';

    protected static string $TableName;

    /**
     * @var array ['table_field'=>'TableField']
     */
    protected static array $propertiesMapping;
    protected static array $dataTransformRules;

    private array $values = [];
    private ?array $loadedValues = null;

    /**
     * int $id as __construct param was removed in version 7
     */
    public function __construct()
    {
        if (empty(static::$TableName)) {
            throw new RuntimeException(
                static::class . ' has no defined table name. Possible misconfiguration. Try to regenerate base models'
            );
        }
        if (!$this->init()) {
            throw new RuntimeException('Model ' . static::class . '::init() failed');
        }
    }


    protected function init(): bool
    {
        // This method called in __construct() and can be overloaded in children
        return true;
    }

    /**
     * @return string
     */
    public static function getSimpleObjectConfigNameRead(): string
    {
        return static::$SimpleObjectConfigNameRead;
    }

    /**
     * @return string
     */
    public static function getSimpleObjectConfigNameWrite(): string
    {
        return static::$SimpleObjectConfigNameWrite;
    }

    /**
     * @return array
     */
    public function getRawValues(): array
    {
        return $this->values;
    }

    /**
     * @return array|null
     */
    public function getLoadedValues(): ?array
    {
        return $this->loadedValues;
    }

    public static function getReadConnection(): PDO
    {
        $c = ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead);
        $c->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        return $c;
    }

    public static function getWriteConnection(): PDO
    {
        $c = ConnectionManager::getConnection(static::$SimpleObjectConfigNameWrite);
        $c->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        return $c;
    }

    public static function getTableName(): string
    {
        return static::$TableName;
    }

    public static function getTableFields(): array
    {
        return array_keys(static::$propertiesMapping);
    }

    public function getIdProperty(): string
    {
        return array_values(static::$propertiesMapping)[0];
    }

    protected function getIdField(): string
    {
        return static::getTableFields()[0];
    }

    public static function isPropertyExist($name): bool
    {
        return in_array($name, static::$propertiesMapping, true);
    }

    public static function isTableFieldExist(string $name): bool
    {
        return array_key_exists($name, static::$propertiesMapping);
    }

    public static function getPropertyField(string $propertyName): string
    {
        if (!static::isPropertyExist($propertyName)) {
            throw new Error('Property ' . $propertyName . ' not exist im model ' . static::class);
        }
        return array_flip(static::$propertiesMapping)[$propertyName];
    }

    public function getFieldProperty(string $tableFieldName): string
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new RuntimeException('Table field ' . $tableFieldName . ' not exist im model ' . static::class);
        }
        return static::$propertiesMapping[$tableFieldName];
    }

    public function isExistInStorage(): bool
    {
        return !empty($this->loadedValues);
    }

    private bool $skipPopulateCaching = false;
    /**
     * Loads model data from storage
     *
     * @param bool $forceLoad
     *
     * @return bool
     */
    protected function load(bool $forceLoad = false): bool
    {
        if (null === $this->{$this->getIdProperty()}) {
            return false;
        }
        if ($forceLoad || !($result = RuntimeCache::getInstance()->get(static::class, $this->{$this->getIdProperty()}))) {
            $this->forceLoad = true;
            try {
                $query = sprintf("SELECT * FROM %s WHERE %s = ?", static::getTableName(), $this->getIdField());
                $db = static::getReadConnection();
                $statement = $db->prepare($query);

                if (!$statement->execute([$this->{$this->getIdProperty()}])) {
                    throw new RuntimeException('Fetch by PK failed: ' . $statement->errorInfo()[2]);
                }
                if ($statement->rowCount() < 1) {
                    $this->loadedValues = [];
                    return false;
                }
                $result = $statement->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
            }

            RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $result);
            $this->skipPopulateCaching = true;
        }
        $this->populate($result);
        return true;
    }

    /**
     * Fills model with supplied array data
     *
     * @param array $data
     * @param bool $applyTransforms
     * @param bool $isNewRecord
     */
    public function populate(array $data, bool $applyTransforms = true, bool $isNewRecord = false)
    {
        foreach ($data as $tableFieldName => $value) {
            if (!static::isTableFieldExist($tableFieldName)) {
                continue;
            }

            $_transformer = $this->getTransformerForField($tableFieldName);

            if ($applyTransforms && !empty($_transformer)) {
                $value = $_transformer['transformerClass']::toProperty($value, $_transformer['transformerParams'] ?? null);
            }

            if (!empty($_transformer) && !$_transformer['transformerClass']::isValidPropertyData($value, $_transformer['transformerParams'] ?? null)) {
                throw new InvalidArgumentException('Bad data (' . $value . ') for property ' . NamingStyle::toCamelCase($tableFieldName, true));
            }

            $propertyName = static::getFieldProperty($tableFieldName);
            $this->$propertyName = $value;
        }
        if (!$isNewRecord) {
            $this->loadedValues = $data;
            if (!$this->skipPopulateCaching){
                RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $data);
                $this->skipPopulateCaching = false;
            }
        }
    }

    private static function setDataTransformForField(string $columnName, array $transformRule): bool
    {
        if (!static::isTableFieldExist($columnName)) {
            throw new InvalidArgumentException('Column ' . $columnName . ' does not exist in table ' . self::getTableName());
        }
        if (empty($transformRule['transformerClass']) || !class_exists($transformRule['transformerClass']) || !class_implements($transformRule['transformerClass'], DataTransformerInterface::class)) {
            throw new InvalidArgumentException($transformRule['transformerClass'] . 'is not valid transformer');
        }
        static::$dataTransformRules[$columnName] = $transformRule;
        return true;
    }

    private function getTransformerForField(string $tableFieldName)
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new InvalidArgumentException('Column ' . $tableFieldName . ' does not exist in table ' . static::getTableName());
        }
        if (empty(static::$dataTransformRules[$tableFieldName]['transformerClass'])) {
            return null;
        }
        if (!class_exists((static::$dataTransformRules[$tableFieldName]['transformerClass']))) {
            return null;
        }
        return static::$dataTransformRules[$tableFieldName];
    }



    /**
     * @param string|PDOStatement $statement
     * @param array $bind
     *
     * @return QueryResult
     * @throws Exception
     */
    public static function factory(PDOStatement|string $statement, array $bind = []): QueryResult
    {
        if (!is_string($statement) && !($statement instanceof PDOStatement)) {
            throw new RuntimeException('Unknown type ' . gettype($statement) . '. Expected string or PDOStatement');
        }
        $sql = null;
        if (is_string($statement)) {
            $sql = $statement;
            $statement = ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead)->prepare($sql);
        }

        $statement->execute($bind);

        $data = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), static::getTableFields())) > 0) {
                throw new RuntimeException('Missing fields ' . implode(', ', $missingFields));
            }
            $entity = new static();
            $entity->populate($row);
            $data[] = $entity;
        }
        return new QueryResult($data, null, $statement);
    }

    public static function one(array $conditions): ?static
    {
        return static::find($conditions)->getElement();
    }

    public static function find(array $conditions): QueryResult
    {
        $query = new Filter($conditions, static::class);
        $stmt = self::getReadConnection()->prepare($query->getSQL());
        $stmt->execute($query->getBind());

        $data = array_map(function (array $row) {
            $record = new static();
            $record->populate($row);
            return $record;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return new QueryResult($data, $query, $stmt);
    }

    public static function getCount(array $conditions): int
    {
        $query = new Filter($conditions, static::class);
        $stmt = self::getReadConnection()->prepare($query->getCountSQL());
        $stmt->execute($query->getBind());
        return (int)$stmt->fetchColumn();
    }

    public function __isset($name): bool
    {
        if (!static::isPropertyExist($name)) {
            return false;
        }

        if (!static::isTableFieldExist($name)) {
            return false;
        }
        return true;
    }

    public function getDataForSave(bool $applyTransforms = true): ?array
    {
        $data = [];
        foreach (array_keys(static::$propertiesMapping) as $tableFieldName) {
            $value = null;
            $property = static::getFieldProperty($tableFieldName);

            if (isset($this->values[$property])) {
                $value = $this->values[$property];
            }

            $transformer = $this->getTransformerForField($tableFieldName);
            if ($applyTransforms && $value !== null && !empty($transformer)) {
                $value = $transformer['transformerClass']::toDatabaseValue($value, $transformer['transformerParams'] ?? null);
            }

            // Make sure null values are not added to the data array
            if ($value !== null) {
                $data[$tableFieldName] = $value;
            }
        }

        return $data;
    }

    public function hasChanges(): bool
    {
        // Get the data for saving in the format it will be stored in the database
        $dataForSave = $this->getDataForSave();

        // If there is no data for saving, consider there are no changes
        if (empty($dataForSave)) {
            return false;
        }

        // Check if there are differing fields between the current values and the data for saving
        $differingFields = array_diff_assoc($dataForSave, $this->loadedValues);
        // If there are differing fields, there are changes
        return !empty($differingFields);
    }

    public function save(bool $force = false): bool
    {
        try {
            // Get data for saving
            $data = $this->getDataForSave();

            // If there is no data to save, return false
            if (empty($data)) {
                return false;
            }

            // Check if updating record in the database is needed
            if ($this->isExistInStorage()) {
                // Check if there are differing fields between current values and loaded values
                if (!$force && !$this->hasChanges()) {
                    // No differing fields, so just return true without updating
                    return true;
                }

                // Update existing record
                $fields = array_keys($data);
                $updateFields = array_map(fn($field) => "$field = ?", $fields);
                $values = array_values($data);
                $values[] = $this->{$this->getIdProperty()};
                $sql = sprintf(
                    "UPDATE %s SET %s WHERE %s = ?",
                    static::getTableName(),
                    implode(', ', $updateFields),
                    $this->getIdField()
                );
            } else {
                // Insert new record
                $fields = array_keys($data);
                $placeholders = array_fill(0, count($fields), '?');
                $values = array_values($data);
                $sql = sprintf(
                    "INSERT INTO %s (%s) VALUES (%s)",
                    static::getTableName(),
                    implode(', ', $fields),
                    implode(', ', $placeholders)
                );
            }

            // Execute the query
            $db = static::getWriteConnection();
            $stmt = $db->prepare($sql);
            if (!$stmt->execute($values)) {
                throw new RuntimeException('Failed to save record: ' . $stmt->errorInfo()[2]);
            }

            // Update cache if a new record is created
            if (!$this->isExistInStorage()) {
                $id = $db->lastInsertId();
                $this->{$this->getIdProperty()} = $id;
                RuntimeCache::getInstance()->put(static::class, $id, $data);
            } else {
                // Update cache of loaded values
                RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $data);
            }

            $this->loadedValues = $data;
            return true;
        } catch (Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete(): bool
    {
        if (!$this->isExistInStorage()) {
            return false;
        }

        try {
            $sql = sprintf(
                "DELETE FROM %s WHERE %s = ?",
                static::getTableName(),
                $this->getIdField()
            );
            $db = static::getWriteConnection();
            $stmt = $db->prepare($sql);
            if (!$stmt->execute([$this->{$this->getIdProperty()}])) {
                throw new RuntimeException('Failed to delete record: ' . $stmt->errorInfo()[2]);
            }

            RuntimeCache::getInstance()->drop(static::class, $this->{$this->getIdField()});
            $this->loadedValues = [];
            return true;
        } catch (Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function __set(string $name, mixed $value)
    {
        if (static::isPropertyExist($name) || static::isTableFieldExist($name)) {
            $propertyName = static::isPropertyExist($name) ? $name : null;
            $fieldName = static::isTableFieldExist($name) ? $name : null;
            $propertyName = $propertyName ?? static::getFieldProperty($fieldName);
            $fieldName = $fieldName ?? static::getPropertyField($propertyName);

            $_transformer = $this->getTransformerForField($fieldName);
            if (!empty($_transformer)) {
                if (
                    !$_transformer['transformerClass']::isValidPropertyData($value, $_transformer['transformerParams'] ?? null) &&
                    $_transformer['transformerClass']::isValidDatabaseData($value, $_transformer['transformerParams'] ?? null)
                ) {
                    $value = $_transformer['transformerClass']::toProperty($value);
                }
                if (!$_transformer['transformerClass']::isValidPropertyData($value, $_transformer['transformerParams'] ?? null)) {
                    if ($fieldName !== 'id' || (!is_numeric($value) && !is_null($value))) {
                        throw new InvalidArgumentException('Bad data for property ' . $name . ' ' . (is_object($value)?get_class($value):gettype($value)));
                    }
                }
            }
            $this->values[$propertyName] = $value;
        }
    }

    public function __get(string $name): mixed
    {
        if (static::isPropertyExist($name) || static::isTableFieldExist($name)) {
            $propertyName = static::isPropertyExist($name) ? $name : null;
            $fieldName = static::isTableFieldExist($name) ? $name : null;
            $fieldName = $fieldName ?? static::getPropertyField($propertyName);
            $propertyName = $propertyName ?? static::getFieldProperty($fieldName);
            if (array_key_exists($propertyName, $this->values)) {
                return $this->values[$propertyName];
            }
        }

        return match ($name) {
            'ReadConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead),
            'WriteConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameWrite),
            'TableName' => static::$TableName,
            'TableFields' => static::getTableFields(),
            'Properties' => array_values(static::$propertiesMapping),
            'SimpleObjectConfigNameRead' => static::$SimpleObjectConfigNameRead,
            'SimpleObjectConfigNameWrite' => static::$SimpleObjectConfigNameWrite,
            default => null,
        };
    }

    public function __toArray(): array
    {
        $result = [];
        foreach ($this->values as $tableFieldName => $value) {
            if (!$propertyName = $this->getFieldProperty($tableFieldName)) {
                continue;
            }
            $result[$propertyName] = $value;
        }
        return $result;
    }

    public function rewind(): void
    {
        reset(static::$propertiesMapping);
    }

    public function current(): mixed
    {
        $property = current(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                return $this->__get($property);
            } catch (Exception) {
            }
        }
        return false;
    }

    public function next(): void
    {
        $property = next(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                $this->__get($property);
                return;
            } catch (Exception) {
            }
        }
    }

    public function valid(): bool
    {
        $key = $this->key();
        return ($key !== null && $key !== false);
    }

    public function key(): null|int|string|bool
    {
        return key(static::$propertiesMapping);
    }

    public function offsetExists(mixed $offset): bool
    {
        return (static::isPropertyExist($offset) || static::isTableFieldExist($offset));
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (static::isPropertyExist($offset) || static::isTableFieldExist($offset)) {
            try {
                return $this->__get($offset);
            } catch (Exception) {
            }
        }
        return false;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        try {
            $this->__set($offset, $value);
        } catch (Exception) {
        }
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    public function count(): int
    {
        return count(static::$propertiesMapping);
    }
}
