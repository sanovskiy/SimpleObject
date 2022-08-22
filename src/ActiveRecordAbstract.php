<?php
/**
 * Created: {13.11.2018}
 *
 * @author    Pavel Terentyev
 * @copyright 2018
 */

namespace Sanovskiy\SimpleObject;


use ArrayAccess;
use Countable;
use Envms\FluentPDO\Query;
use Iterator;
use NilPortugues\Sql\QueryBuilder\Builder\GenericBuilder;
use RuntimeException;
use Sanovskiy\SimpleObject\FieldValues\NullValue;

/**
 * Class ActiveRecordAbstract
 * @package Sanovskiy\SimpleObject
 * @property integer $Id
 * @property string $TableName
 * @property array $TableFields
 * @property array $Properties
 * @property string $SimpleObjectConfigNameRead
 * @property string $SimpleObjectConfigNameWrite
 * @property ?PDO $DBConRead
 * @property ?PDO $DBConWrite
 *
 */
class ActiveRecordAbstract implements Iterator, ArrayAccess, Countable
{
    protected static string $SimpleObjectConfigNameRead = 'default';
    protected static string $SimpleObjectConfigNameWrite = 'default';
    /**
     * @var string
     */
    protected static string $TableName = '';
    /**
     * Model properties to field mapping
     * @var array
     */
    protected static array $propertiesMapping = [];
    protected static array $dataTransformRules = [];

    protected bool $existInStorage = false;
    protected array $values = [];
    protected array $loadedValues = [];

    /**
     * ActiveRecordAbstract constructor.
     *
     * @param int|null $id
     *
     */
    public function __construct(?int $id = null)
    {
        if (!$this->init()) {
            throw new RuntimeException('Model ' . static::class . '::init() failed');
        }
        if ($id === null) {
            $this->{$this->getIdProperty()} = null;
            return;
        }

        $this->{$this->getIdProperty()} = $id;
        $this->load();
    }

    /**
     * @return bool
     */

    protected function init(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    protected function getIdProperty(): string
    {
        return array_values(static::$propertiesMapping)[0];
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
        if ($forceLoad || !($result = RuntimeCache::getInstance()->get(
                static::class,
                $this->{$this->getIdProperty()}
            ))) {
            try {
                $builder = new GenericBuilder();
                $query = $builder->select();
                $query->setTable(static::$TableName)->setColumns(array_keys(static::$propertiesMapping));
                $query->where()->eq($this->getIdField(), $this->{$this->getIdField()});
                $stmt = $this->DBConRead->prepare($builder->writeFormatted($query));
                if (!$stmt->execute($builder->getValues())) {
                    throw new RuntimeException('Fetch by PK failed: ' . $stmt->errorInfo()[2]);
                }
                if ($stmt->rowCount() < 1) {
                    $this->existInStorage = false;
                    return false;
                }
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
            }
            $this->existInStorage = true;
            RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $result);
            $this->loadedValues = $result;
        }
        $this->populate($result);
        return true;
    }

    /**
     * @return string
     */
    protected function getIdField(): string
    {
        return array_keys(static::$propertiesMapping)[0];
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
            if ($applyTransforms && $transformations = $this->getReadTransforms($tableFieldName)) {
                foreach ($transformations as $transformation => $options) {
                    $value = Transform::apply_transform($transformation, $value, $options);
                }
            }
            $this->values[$tableFieldName] = $value;
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isTableFieldExist(string $name): bool
    {
        return array_key_exists($name, static::$propertiesMapping);
    }

    /**
     * @param string $fieldName
     *
     * @return array
     */
    public function getReadTransforms(string $fieldName): array
    {
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists(
                'read',
                static::$dataTransformRules[$fieldName]
            )) {
            return [];
        }
        return static::$dataTransformRules[$fieldName]['read'];
    }

    /**
     * @param string|\PDOStatement $source
     * @param array $bind
     *
     * @return Collection<static>
     */
    public static function factory(\PDOStatement|string $source, array $bind = []): Collection
    {
        if (!is_string($source) && !($source instanceof \PDOStatement)) {
            throw new RuntimeException('Unknown type ' . gettype($source) . '. Expected string or PDOStatement');
        }

        if (is_string($source)) {
            $source = Util::getConnection(static::$SimpleObjectConfigNameRead)->prepare($source);
        }

        $source->execute($bind);

        $collection = new Collection();
        $collection->setClassName(static::class);

        while ($row = $source->fetch(\PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), array_keys(static::$propertiesMapping))) > 0) {
                throw new RuntimeException('Missing fields ' . implode(', ', $missingFields));
            }
            $entity = new static();
            $entity->populate($row);
            $collection->push($entity);
        }

        return $collection;
    }

    /**
     * @param string $field
     * @param string $transformName
     * @param mixed $transformOptions
     *
     * @return bool
     * @noinspection PhpUnused It's used
     */
    public static function setReadTransform(string $field, string $transformName, mixed $transformOptions): bool
    {
        if (!static::isTableFieldExist($field)) {
            return false;
        }
        static::$dataTransformRules[$field]['read'][$transformName] = $transformOptions;
        return true;
    }

    /**
     * @param $field
     * @param $transformName
     * @param $transformOptions
     *
     * @return bool
     * @noinspection PhpUnused - It's used
     */
    public static function setWriteTransform($field, $transformName, $transformOptions): bool
    {
        if (!static::isTableFieldExist($field)) {
            return false;
        }
        static::$dataTransformRules[$field]['write'][$transformName] = $transformOptions;
        return true;
    }

    /**
     * @return string
     * @noinspection PhpUnused - It's used
     */
    public static function getTableName(): string
    {
        if (static::$TableName === '') {
            throw new RuntimeException(
                static::class . ' has no defined table name. Possible misconfiguration. Try to regenerate base models'
            );
        }
        return static::$TableName;
    }

    /**
     * @param array $conditions
     *
     * @return static|null
     */
    public static function one(array $conditions): ?static
    {
        return static::find($conditions)->getElement();
    }

    /**
     * @param array $conditions
     * @return Collection|Query
     */
    public static function find(array $conditions): Collection|Query
    {
        $builder = new GenericBuilder();

        $select = $builder->select();
        $select->setTable(static::$TableName)->setColumns(array_keys(static::$propertiesMapping));
        foreach ($conditions as $condition => $value) {
            if (!in_array(
                $condition,
                array_merge(array_keys(static::$propertiesMapping), array_values(static::$propertiesMapping))
            )) {
                switch (strtolower($condition)) {
                    case ':order':
                        if (!is_array($value)) {
                            $select->orderBy($value);
                            break;
                        }
                        $select->orderBy($value[0], $value[1]);
                        break;
                    case ':limit':
                        if (is_array($value)) {
                            $select->limit($value[0], $value[1]);
                        } else {
                            $select->limit($value);
                        }
                        break;
                    default:
                        break;
                }
                continue;
            }
            $column = $condition;
            if (!array_key_exists($column, static::$propertiesMapping)) {
                if (!in_array($column, static::$propertiesMapping)) {
                    // Column not found
                    continue;
                }
                $column = array_search($column, static::$propertiesMapping);
            }
            if (!is_array($value)) {
                $select->where()->eq($column, $value);
                continue;
            }
            switch (count($value)) {
                case 2:
                    $compare = strtolower($value[0]);
                    $value1 = $value[1];
                    switch ($compare) {
                        case 'null':
                        case 'is null':
                            $select->where()->isNull($column);
                            break;
                        case 'not null':
                        case 'is not null':
                            $select->where()->isNotNull($column);
                            break;
                        case '=':
                        case 'eq':
                            $select->where()->eq($column, $value1);
                            break;
                        case '<':
                        case 'lt':
                            $select->where()->lessThan($column, $value1);
                            break;
                        case '>':
                        case 'gt':
                            $select->where()->greaterThan($column, $value1);
                            break;
                        case '<=':
                        case 'lteq':
                            $select->where()->lessThanOrEqual($column, $value1);
                            break;
                        case '>=':
                        case 'gteq':
                            $select->where()->greaterThanOrEqual($column, $value1);
                            break;
                        case '<>':
                        case '!=':
                            $select->where()->notEquals($column, $value1);
                            break;
                        case 'like':
                            $select->where()->like($column, $value1);
                            break;
                        case 'not like':
                            $select->where()->notLike($column, $value1);
                            break;
                        case 'in':
                            $select->where()->in($column, $value1);
                            break;
                        case 'not in':
                        case 'notin':
                        case '!in':
                            $select->where()->notin($column, $value1);
                            break;
                    }
                    break;
                case 3:
                    $compare = $value[0];
                    $value1 = $value[1];
                    $value2 = $value[2];
                    switch ($compare) {
                        case 'between':
                            $select->where()->between($column, $value1, $value2);
                            break;
                        case 'not between':
                            $select->where()->notBetween($column, $value1, $value2);
                            break;
                    }
                    break;
                default:
                    // not in format
                    continue 2;
            }
        }
        $result = new Collection();
        $stmt = static::getDBConRead()->prepare($builder->writeFormatted($select));
        $stmt->execute($builder->getValues());
        if ($stmt->errorCode() > 0) {
            throw new RuntimeException($stmt->errorInfo()[2]);
        }

        while ($_row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $entity = new static();
            $entity->populate($_row);
            $result->push($entity);
        }
        return $result;
    }

    /**
     * @return PDO
     * @noinspection PhpUnused - It's used
     */
    public static function getDBConRead(): PDO
    {
        return Util::getConnection(self::$SimpleObjectConfigNameRead);
    }

    /**
     * @param $name
     *
     * @return bool
     * @noinspection PhpUnused - It's used
     */
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

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isPropertyExist(string $name): bool
    {
        return in_array($name, static::$propertiesMapping, true);
    }

    /**
     * @noinspection PhpUnused - It's used
     */
    public function reload()
    {
        $this->load(true);
    }

    /**
     * @param bool $force
     *
     * @return bool
     */
    public function save(bool $force = false): bool
    {
        $builder = new GenericBuilder();
        $data = $this->getDataForSave();
        // Filtering nulls
        $data = array_filter($data, function ($e) {
            return $e !== null;
        });
        $data = array_map(function($e){
            if ($e instanceof NullValue){
                return null;
            }
            return $e;
        },$data);
        unset($data[static::getIdField()]);
        try {
            // Solution for booleans that slipped through self::getDataForSave() magic
            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 'true' : 'false';
                }
            }
            switch ($this->isExistInStorage()) {
                case true: // Update existing record
                    if (!$force) {
                        $data = array_diff(array_intersect_key($data, $this->loadedValues), $this->loadedValues);
                        if (empty($data)) {
                            return true;
                        }
                    }
                    $update = $builder->update();
                    $update->setTable(static::$TableName);
                    $update->setValues($data);
                    $update->where()->eq(static::getIdField(), $this->__get(static::getIdField()));
                    $stmt = static::getDBConWrite()->prepare($builder->writeFormatted($update));
                    if (!$stmt->execute($builder->getValues())) {
                        $errorInfo = $stmt->errorInfo();
                        throw new RuntimeException($errorInfo[2]);
                    }
                    break;
                default: // Create new record
                    $insert = $builder->insert();
                    $insert->setTable(static::$TableName);
                    $insert->setValues($data);
                    $stmt = static::getDBConWrite()->prepare($builder->writeFormatted($insert));
                    if (!$stmt->execute($builder->getValues())) {
                        $errorInfo = $stmt->errorInfo();
                        throw new RuntimeException($errorInfo[2]);
                    }
                    $id = static::getDBConWrite()->lastInsertId();
                    $this->values[static::getIdField()] = $id;
                    $this->loadedValues = $this->values;
                    $this->existInStorage = true;
                    break;
            }
        } catch (\Envms\FluentPDO\Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
        return true;
    }

    /**
     * @param bool $applyTransforms
     *
     * @return array|null
     */
    public function getDataForSave(bool $applyTransforms = true): ?array
    {
        $data = [];
        foreach (array_keys(static::$propertiesMapping) as $tableFieldName) {
            $value = null;
            if (isset($this->values[$tableFieldName])) {
                $value = $this->values[$tableFieldName];
            }

            if ($applyTransforms && $value !== null && $transformations = $this->getWriteTransforms($tableFieldName)) {
                foreach ($transformations as $transformation => $options) {
                    $value = Transform::apply_transform($transformation, $value, $options);
                }
            }
            /*if (null === $value) {
                continue;
            }*/
            $data[$tableFieldName] = $value;
        }
        return $data;
    }

    /**
     * @param string $fieldName
     *
     * @return array
     */
    public function getWriteTransforms(string $fieldName): array
    {
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists(
                'write',
                static::$dataTransformRules[$fieldName]
            )) {
            return [];
        }
        return static::$dataTransformRules[$fieldName]['write'];
    }

    /**
     * @return bool
     */
    public function isExistInStorage(): bool
    {
        //return !!$this->existInStorage;
        return !empty($this->loadedValues);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
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
            'DBConRead' => Util::getConnection(static::$SimpleObjectConfigNameRead),
            'DBConWrite' => Util::getConnection(static::$SimpleObjectConfigNameWrite),
            'TableName' => static::$TableName,
            'TableFields' => array_keys(static::$propertiesMapping),
            'Properties' => array_values(static::$propertiesMapping),
            'SimpleObjectConfigNameRead' => static::$SimpleObjectConfigNameRead,
            'SimpleObjectConfigNameWrite' => static::$SimpleObjectConfigNameWrite,
            default => null,
        };
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
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

    /**
     * @param string $propertyName
     *
     * @return string
     */
    public static function getPropertyField(string $propertyName): string
    {
        if (!static::isPropertyExist($propertyName)) {
            throw new RuntimeException('Property ' . $propertyName . ' not exist im model ' . static::class);
        }
        return array_flip(static::$propertiesMapping)[$propertyName];
    }

    /**
     * @return PDO
     * @noinspection PhpUnused - It's used
     */
    public static function getDBConWrite(): PDO
    {
        return Util::getConnection(self::$SimpleObjectConfigNameWrite);
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->isExistInStorage()) {
            return false;
        }
        try {
            $builder = new GenericBuilder();
            $delete = $builder->delete();
            $delete->setTable(static::$TableName);
            $delete->where()->eq(static::getIdField(), $this->__get(static::getIdField()));
            $stmt = static::getDBConWrite()->prepare($builder->writeFormatted($delete));
            if (!$stmt->execute($builder->getValues())) {
                $errorInfo = $stmt->errorInfo();
                throw new RuntimeException($errorInfo[2]);
            }
        } catch (\Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
        RuntimeCache::getInstance()->drop(static::class, $this->{$this->getIdField()});
        $this->loadedValues = [];
        $this->existInStorage = false;
        return true;
    }

    /**
     * @return array
     */
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

    /**
     * @param string $tableFieldName
     *
     * @return string
     */
    public function getFieldProperty(string $tableFieldName): string
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new RuntimeException('Table field ' . $tableFieldName . ' not exist im model ' . static::class);
        }
        return static::$propertiesMapping[$tableFieldName];
    }

    /**
     *
     */
    public function rewind(): void
    {
        reset(static::$propertiesMapping);
    }

    /**
     * @return mixed
     */
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

    /**
     * @return void
     */
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

    /**
     * @return bool
     */
    public function valid(): bool
    {
        $key = $this->key();
        return ($key !== null && $key !== false);
    }

    /**
     * @return int|string|null|bool
     */
    public function key(): null|int|string|bool
    {
        return key(static::$propertiesMapping);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return (static::isPropertyExist($offset) || static::isTableFieldExist($offset));
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
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

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        try {
            $this->__set($offset, $value);
        } catch (Exception) {
        }
    }

    /**
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count(static::$propertiesMapping);
    }

}