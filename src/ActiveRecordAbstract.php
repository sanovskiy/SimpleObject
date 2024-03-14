<?php

namespace Sanovskiy\SimpleObject;

use ArrayAccess;
use Countable;
use Error;
use Exception;
use Iterator;
use PDO;
use PDOStatement;
use RuntimeException;
use Sanovskiy\SimpleObject\Collections\QueryResult;
use Sanovskiy\SimpleObject\Query\Filter;
use Sanovskiy\SimpleObject\Relations\HasOne;
use Sanovskiy\SimpleObject\Relations\HasMany;

/**
 * @property int $Id Identity
 */
class ActiveRecordAbstract implements Iterator, ArrayAccess, Countable
{
    const RELATION_SUFFIX = 'Relation';

    protected static string $SimpleObjectConfigNameRead = 'default';
    protected static string $SimpleObjectConfigNameWrite = 'default';

    protected static string $TableName;

    /**
     * @var array ['table_field'=>'TableField']
     */
    protected static array $propertiesMapping;
    protected static array $dataTransformRules;

    /**
     * @var array ['table_name'=>['local_field','remote_field'],'other_table_name'=>['other_local_field','other_remote_field'],...]
     */
    protected static array $tableRelations = [];

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

    /**
     * Defines a "one-to-many" relationship
     * @param string $relatedModelClass The class of the related model
     * @param string $foreignKey The foreign key in the related model
     * @param string $localKey The local key in the current model
     * @return HasMany
     */
    public function hasMany(string $relatedModelClass, string $foreignKey, string $localKey): HasMany
    {
        return new HasMany($relatedModelClass, $foreignKey, $localKey, $this->Id);
    }

    /**
     * Defines a "one-to-many" relationship
     * @param string $relatedModelClass The class of the related model
     * @param string $localKey The local key in the current model
     * @return HasOne
     */
    public function hasOne(string $relatedModelClass, string $localKey): HasOne
    {
        return new HasOne($relatedModelClass, 'Id', $localKey, $this->$localKey);
    }


    public function isExistInStorage(): bool
    {
        return !empty($this->loadedValues);
    }

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
                $result = $statement->fetch(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
            }

            RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $result);
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
        if (!$isNewRecord) {
            $this->loadedValues = $data;
        }
        foreach ($data as $tableFieldName => $value) {
            if (!static::isTableFieldExist($tableFieldName)) {
                continue;
            }
            if ($applyTransforms && !empty(self::$dataTransformRules[$tableFieldName]['transformerClass']) && class_exists((self::$dataTransformRules[$tableFieldName]['transformerClass']))) {
                $value = call_user_func([self::$dataTransformRules[$tableFieldName]['transformerClass'], 'toProperty'], [$value, (self::$dataTransformRules[$tableFieldName]['transformerParams'] ?? null)]);
            }
            $this->values[$tableFieldName] = $value;
        }
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
        $query = new Filter(static::class, $conditions);
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
        $query = new Filter(static::class, $conditions);
        $stmt = self::getReadConnection()->prepare($query->getCountSQL());
        $stmt->execute($query->getBind());
        return (int)$stmt->fetchColumn(0);
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
            if (isset($this->values[$tableFieldName])) {
                $value = $this->values[$tableFieldName];
            }

            if ($applyTransforms && $value !== null && isset(static::$dataTransformRules[$tableFieldName])) {
                $transformerClass = static::$dataTransformRules[$tableFieldName]['transformerClass'] ?? null;
                $transformerParams = static::$dataTransformRules[$tableFieldName]['transformerParams'] ?? null;
                if ($transformerClass && class_exists($transformerClass)) {
                    $value = $transformerClass::toDatabaseValue($value, $transformerParams);
                }
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
        if (static::isPropertyExist($name)) {
            $this->values[static::getPropertyField($name)] = $value;
            return;
        }
        if (static::isTableFieldExist($name)) {
            $this->values[$name] = $value;
        }
    }

    public function __get(string $name): mixed
    {
        if (static::isPropertyExist($name)) {
            if (!array_key_exists(static::getPropertyField($name), $this->values)) {
                return null;
            }
            return $this->values[static::getPropertyField($name)];
        }
        if (static::isTableFieldExist($name)) {
            if (!array_key_exists($name, $this->values)) {
                return null;
            }
            return $this->values[$name];
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

    protected function getRelatedModel(Relation $relationship): ?ActiveRecordAbstract
    {
        $relatedModelClass = $relationship->getRelatedModel();
        /** @var ActiveRecordAbstract $relatedModelClass */
        if ($this->{$relationship->getLocalKey()}) {
            return $relatedModelClass::one([$relationship->getForeignKey() => $this->{$relationship->getLocalKey()}]);
        }
        return null;
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
