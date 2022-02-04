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
use JetBrains\PhpStorm\Pure;

/**
 * Class ActiveRecordAbstract
 * @package Sanovskiy\SimpleObject
 * @property integer $Id
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
    /**
     * @var ?PDO
     */
    protected ?PDO $DBConWrite;
    /**
     * @var ?PDO
     */
    protected ?PDO $DBConRead;
    /**
     * @var bool
     */
    protected bool $existInStorage = false;
    protected array $values = [];
    protected array $loadedValues = [];

    /**
     * ActiveRecordAbstract constructor.
     *
     * @param int|null $id
     *
     * @throws Exception
     */
    public function __construct(?int $id = null)
    {
        if (!$this->init()) {
            throw new Exception('Model ' . static::class . '::init() failed');
        }
        $this->DBConRead = Util::getConnection(static::$SimpleObjectConfigNameRead);
        $this->DBConWrite = Util::getConnection(static::$SimpleObjectConfigNameWrite);
        if ($id === null) {
            $this->Id = null;
            return;
        }

        $this->Id = $id;
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
     * Loads model data from storage
     *
     * @param bool $forceLoad
     *
     * @return bool
     * @throws Exception
     */
    protected function load(bool $forceLoad = false): bool
    {
        if (null === $this->Id) {
            return false;
        }
        if ($forceLoad || !($result = RuntimeCache::getInstance()->get(static::class, $this->Id))) {
            try {
                $query = static
                    ::getReadQuery()
                    ->from(static::$TableName)
                    ->select(array_keys(static::$propertiesMapping), true)
                    ->where('id = ?', $this->__get($this->getFieldProperty('id')));
                if (!$result = $query->fetch()) {
                    $this->existInStorage = false;
                    return false;
                }
            } catch (\Envms\FluentPDO\Exception $e) {
                throw new Exception('FluentPDO error: ' . $e->getMessage(), $e->getCode(), $e);
            }
            $this->existInStorage = true;
            RuntimeCache::getInstance()->put(static::class, $this->Id, $result);
            $this->loadedValues = $result;
        }
        $this->populate($result);
        return true;
    }

    /**
     * @return Query
     */
    protected static function getReadQuery(): Query
    {
        $q = new Query(Util::getConnection(static::$SimpleObjectConfigNameRead));
        $q->throwExceptionOnError(true);
        $q->convertReadTypes(true);

        return $q;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws Exception
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
     * @throws Exception
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
     * @param string $name
     *
     * @return bool
     */
    public static function isPropertyExist(string $name): bool
    {
        return in_array($name, static::$propertiesMapping, true);
    }

    /**
     * @param string $propertyName
     *
     * @return string
     * @throws Exception
     */
    public static function getPropertyField(string $propertyName): string
    {
        if (!static::isPropertyExist($propertyName)) {
            throw new Exception('Property ' . $propertyName . ' not exist im model ' . static::class);
        }
        return array_flip(static::$propertiesMapping)[$propertyName];

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
     * @param string $tableFieldName
     *
     * @return string
     * @throws Exception
     */
    public function getFieldProperty(string $tableFieldName): string
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new Exception('Table field ' . $tableFieldName . ' not exist im model ' . static::class);
        }
        return static::$propertiesMapping[$tableFieldName];
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
     * @param string $fieldName
     *
     * @return array
     */
    public function getReadTransforms(string $fieldName): array
    {
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists('read',
                static::$dataTransformRules[$fieldName])) {
            return [];
        }
        return static::$dataTransformRules[$fieldName]['read'];
    }

    /**
     * @param string|\PDOStatement $source
     * @param array $bind
     *
     * @return Collection<static>
     * @throws Exception
     */
    public static function factory(\PDOStatement|string $source, array $bind = []): Collection
    {
        if (!is_string($source) && !($source instanceof \PDOStatement)) {
            throw new Exception('Unknown type ' . gettype($source) . '. Expected string or PDOStatement');
        }

        if (is_string($source)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $source = Util::getConnection(static::$SimpleObjectConfigNameRead)->prepare($source);
        }

        $source->execute($bind);

        $collection = new Collection();
        $collection->setClassName(static::class);

        while ($row = $source->fetch(\PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), array_keys(static::$propertiesMapping))) > 0) {
                throw new Exception('Missing fields ' . implode(', ', $missingFields));
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
     * @throws Exception
     * @noinspection PhpUnused - It's used
     */
    public static function getTableName(): string
    {
        if (static::$TableName === '') {
            throw new Exception(static::class . ' has no defined table name. Possible misconfiguration');
        }
        return static::$TableName;
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
     * @return PDO
     * @noinspection PhpUnused - It's used
     */
    public static function getDBConWrite(): PDO
    {
        return Util::getConnection(self::$SimpleObjectConfigNameWrite);
    }

    /**
     * @param array $conditions
     *
     * @return ActiveRecordAbstract|null
     * @throws Exception
     */
    public static function one(array $conditions): ?ActiveRecordAbstract
    {
        return static::find($conditions)->getElement();
    }

    /**
     * @param array $conditions
     * @return Collection|Query
     * @throws Exception
     */
    public static function find(array $conditions): Collection|Query
    {
        try {
            $select = static
                ::getReadQuery()
                ->from(static::$TableName);
        } catch (\Envms\FluentPDO\Exception $e) {
            throw new Exception('FluentPDO error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        $select->select(array_keys(static::$propertiesMapping), true);

        foreach ($conditions as $condition => $value) {
            switch (strtolower($condition)) {
                case '(columns)':
                    $select->select($value, true);
                    break;
                case '(order)':
                    $select->orderBy($value);
                    break;
                case '(limit)':
                    $select->limit($value);
                    break;
                case '(offset)':
                    $select->offset($value);
                    break;
                case '(having)':
                    $select->having($value);
                    break;
                case '(group)':
                    continue 2;
                case '(null)':
                    $select->where($value . ' IS NULL');
                    continue 2;
                case '(not null)':
                    $select->where($value . ' IS NOT NULL');
                    continue 2;
                /*                case '(!!simulate)':
                                    $returnQuery = true;
                                    break;*/
                default:
                    $select->where($condition, $value);
                    break;
            }
        }
        /*        if ($returnQuery) {
                    return $select;
                }*/
        $result = new Collection();
        foreach ($select as $_row) {
            $entity = new static();
            $entity->populate($_row);
            $result->push($entity);
        }
        return $result;
    }

    /**
     * @param $name
     *
     * @return bool
     * @noinspection PhpUnused - It's used
     */
    #[Pure] public function __isset($name): bool
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
     * @throws Exception
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
     * @throws Exception
     */
    public function save(bool $force = false): bool
    {
        $data = $this->getDataForSave();
        // Filtering nulls
        $data = array_filter($data, function ($e) {
            return $e !== null;
        });
        unset($data['id']);
        try {
            // Solution for booleans that slipped through self::getDataForSave() magic
            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 'true' : 'false';
                }
            }
            switch ($this->isExistInStorage()) {
                case false: // Create new record
                    $insert = static::getWriteQuery()
                        ->insertInto(static::$TableName)
                        ->values($data);

                    if (!($id = $insert->execute())) {
                        return false;
                    }
                    $this->values['id'] = $id;
                    $this->loadedValues = $this->values;
                    $this->existInStorage = true;
                    break;
                case true: // Update existing record

                    if (!$force) {
                        $data = array_diff(array_intersect_key($data, $this->loadedValues), $this->loadedValues);
                        if (empty($data)) {
                            return true;
                        }
                    }

                    $update = static::getWriteQuery()
                        ->update(static::$TableName)
                        ->set($data)
                        ->where('id = ?', $this->__get('id'));

                    if (!$update->execute(true)) {
                        return false;
                    }
                    break;
            }
        } catch (\Envms\FluentPDO\Exception $e) {
            throw new Exception('FluentPDO error: ' . $e->getMessage(), $e->getCode(), $e);
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
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists('write',
                static::$dataTransformRules[$fieldName])) {
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
     * @return Query
     */
    protected static function getWriteQuery(): Query
    {
        $q = (new Query(Util::getConnection(static::$SimpleObjectConfigNameWrite)));
        $q->throwExceptionOnError(true);
        $q->convertWriteTypes(true);

        return $q;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        if (!$this->isExistInStorage()) {
            return false;
        }
        try {
            $success = !!static::getWriteQuery()->deleteFrom(static::$TableName)->where('id = ?',
                $this->__get('id'))->execute();
        } catch (\Envms\FluentPDO\Exception $e) {
            throw new Exception('FluentPDO error: ' . $e->getMessage(), $e->getCode(), $e);
        }
        if ($success) {
            RuntimeCache::getInstance()->drop(static::class, $this->__get('id'));
        }
        return $success;
    }

    /**
     * @return array
     * @throws Exception
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
     * @return mixed
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
    #[Pure] public function valid(): bool
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
    #[Pure] public function offsetExists(mixed $offset): bool
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