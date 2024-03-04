<?php

namespace Sanovskiy\SimpleObject;

use ArrayAccess;
use Countable;
use Error;
use Exception;
use Iterator;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Sanovskiy\Utility\NamingStyle;

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
     * id as __construct param is removed in version 7
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

    public function getIdProperty(): string
    {
        return array_values(static::$propertiesMapping)[0];
    }

    protected function getIdField(): string
    {
        return array_keys(static::$propertiesMapping)[0];
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
                $query = "SELECT * FROM " . static::getTableName() . " WHERE " . $this->getIdField() . " = ?";
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
            // TODO: Apply value transformations
            $this->values[$tableFieldName] = $value;
        }
    }

    /**
     * @param string|PDOStatement $source
     * @param array $bind
     *
     * @return Collection
     */
    public static function factory(PDOStatement|string $source, array $bind = []): Collection
    {
        if (!is_string($source) && !($source instanceof PDOStatement)) {
            throw new RuntimeException('Unknown type ' . gettype($source) . '. Expected string or PDOStatement');
        }

        if (is_string($source)) {
            $source = ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead)->prepare($source);
        }

        $source->execute($bind);

        $collection = new Collection();
        $collection->setClassName(static::class);

        while ($row = $source->fetch(PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), array_keys(static::$propertiesMapping))) > 0) {
                throw new RuntimeException('Missing fields ' . implode(', ', $missingFields));
            }
            $entity = new static();
            $entity->populate($row);
            $collection->push($entity);
        }

        return $collection;
    }

    public static function one(array $conditions): ?static
    {
        return static::find($conditions)->getElement();
    }

    public static function find(array $conditions): Collection
    {
        // TODO: Add filtering logic
        return new Collection();
    }

    public static function getCount(array $conditions): int
    {
        // TODO: Add filtering logic and count result rows
        return (int)0;
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

    public function save(bool $force = false): bool
    {
        // TODO: Add save logic
        return true;
    }

    public function delete(): bool
    {
        if (!$this->isExistInStorage()) {
            return false;
        }

        try {
            // TODO: add logic for delete
        } catch (Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        RuntimeCache::getInstance()->drop(static::class, $this->{$this->getIdField()});
        $this->loadedValues = [];
        return true;
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

        if ($relationship = $this->getRelationship($name)) {
            return $this->getRelatedModel($relationship);
        }

        return match ($name) {
            'ReadConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead),
            'WriteConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameWrite),
            'TableName' => static::$TableName,
            'TableFields' => array_keys(static::$propertiesMapping),
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
        if ($this->{$relationship->getLocalKey()}){
            return $relatedModelClass::one([$relationship->getForeignKey()=>$this->{$relationship->getLocalKey()}]);
        }
        return null;
    }

    private static array $relationObjects = [];

    public final static function relationships(): array
    {
        if (!empty(static::$tableRelations)){
            if (empty(self::$relationObjects[static::class])){
                foreach (static::$tableRelations as $tableName => $relData){
                    $namespace = implode('\\', array_slice(explode('\\', static::class), 0, -1));
                    $relatedModel = $namespace.'\\'.NamingStyle::toCamelCase($tableName,true);
                    self::$relationObjects[static::class][] = new Relation($relData[0],$relData[1],$relatedModel);
                }
            }
            return self::$relationObjects[static::class];
        }
        return [];
    }

    protected function getRelationship(string $name): ?Relation
    {
        $relationships = static::relationships();
        /** @var Relation $relationship */
        foreach ($relationships as $relationship) {
            if ($relationship->getRelatedModelName(). self::RELATION_SUFFIX === $name ) {
                return $relationship;
            }
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