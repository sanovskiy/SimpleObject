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
use Iterator;
use NilPortugues\Sql\QueryBuilder\Builder\GenericBuilder;
use NilPortugues\Sql\QueryBuilder\Manipulation\QueryException;
use RuntimeException;

/**
 * Class ActiveRecordAbstract
 * @package Sanovskiy\SimpleObject
 * @property integer $Id
 */
class ActiveRecordAbstract implements Iterator, ArrayAccess, Countable
{
    /**
     * @var string
     */
    protected static $SimpleObjectConfigNameRead = 'default';
    /**
     * @var string
     */
    protected static $SimpleObjectConfigNameWrite = 'default';
    /**
     * @var string
     */
    protected static $TableName = '';
    /**
     * Model properties to field mapping
     * @var array
     */
    protected static $propertiesMapping = [];
    protected static $dataTransformRules = [];
    /**
     * @var ?PDO
     */
    protected $DBConWrite;
    /**
     * @var ?PDO
     */
    protected $DBConRead;

    /**
     * @var bool
     */
    protected $existInStorage = false;
    /**
     * @var array
     */
    protected $values = [];
    /**
     * @var array
     */
    protected $loadedValues = [];

    /**
     * ActiveRecordAbstract constructor.
     *
     * @param int|null $id
     *
     * @throws Exception
     */
    public function __construct($id = null)
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

    protected function init()
    {
        return true;
    }

    public static function getIdField()
    {
        return array_keys(static::$propertiesMapping)[0];
    }

    /**
     * Loads model data from storage
     *
     * @param bool $forceLoad
     *
     * @return bool
     * @throws Exception
     */
    protected function load($forceLoad = false)
    {
        if (null === $this->Id) {
            return false;
        }
        if ($forceLoad || !($result = RuntimeCache::getInstance()->get(static::class, $this->Id))) {
            try {
                $builder = new GenericBuilder();
                $query = $builder->select();
                $query->setTable(static::$TableName)->setColumns(array_keys(static::$propertiesMapping));
                $query->where()->eq(static::getIdField(),$this->__get($this->getFieldProperty(static::getIdField())));
                $stmt = static::getDBConRead()->prepare($builder->writeFormatted($query));
                if(!$stmt->execute($builder->getValues())){
                    $errorInfo = $stmt->errorInfo();
                    throw new RuntimeException($errorInfo[2]);
                }
                if ($stmt->rowCount()<1) {
                    $this->existInStorage = false;
                    return false;
                }
            } catch (\Exception $e) {
                throw new Exception('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
            }
            $this->existInStorage = true;
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            RuntimeCache::getInstance()->put(static::class, $this->Id, $result);
            $this->loadedValues = $result;
        }
        $this->populate($result);
        return true;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
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

        switch ($name) {
            case 'TableName':
                return static::$TableName;
            case 'TableFields':
                return array_keys(static::$propertiesMapping);
            case 'Properties':
                return array_values(static::$propertiesMapping);
            case 'SimpleObjectConfigNameRead':
                return static::$SimpleObjectConfigNameRead;
            case 'SimpleObjectConfigNameWrite':
                return static::$SimpleObjectConfigNameWrite;
            default:
                return null;
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     * @throws Exception
     */
    public function __set($name, $value)
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
    public static function isPropertyExist($name)
    {
        return in_array($name, static::$propertiesMapping, true);
    }

    /**
     * @param string $propertyName
     *
     * @return string
     * @throws Exception
     */
    public static function getPropertyField($propertyName)
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
    public static function isTableFieldExist($name)
    {
        return array_key_exists($name, static::$propertiesMapping);
    }

    /**
     * @param string $tableFieldName
     *
     * @return string
     * @throws Exception
     */
    public function getFieldProperty($tableFieldName)
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
    public function populate($data, $applyTransforms = true, $isNewRecord = false)
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
    public function getReadTransforms($fieldName)
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
     * @throws Exception
     */
    public static function factory($source, array $bind = [])
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
    public static function setReadTransform($field, $transformName, $transformOptions)
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
    public static function setWriteTransform($field, $transformName, $transformOptions)
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
    public static function getTableName()
    {
        if (static::$TableName === '') {
            throw new Exception(static::class . ' has no defined table name. Possible misconfiguration');
        }
        return static::$TableName;
    }

    /**
     * @param array $conditions
     *
     * @return ActiveRecordAbstract|null
     * @throws Exception
     * @throws QueryException
     */
    public static function one(array $conditions)
    {
        return static::find($conditions)->getElement();
    }

    /**
     * @param array $conditions
     * @return Collection
     * @throws Exception
     * @throws QueryException
     */
    public static function find(array $conditions)
    {
        $builder = new GenericBuilder();

        $select = $builder->select();
        $select->setTable(static::$TableName)->setColumns(array_keys(static::$propertiesMapping));
        foreach ($conditions as $condition => $value) {
            $condition = strtolower($condition);
            if (!in_array(
                $condition,
                array_merge(array_keys(static::$propertiesMapping), array_values(static::$propertiesMapping))
            )) {
                switch (strtolower($condition)) {
                    case ':columns':
                        $select->setColumns($value);
                        break;
                    case ':order':
                        if (!is_array($value)) {
                            $select->orderBy($value);
                            break;
                        }
                        $select->orderBy($value[0], $value[1]);
                        break;
                    case ':limit':
                        $select->limit($value);
                        break;
                    case ':having':
                        $select->having($value);
                        break;
                    case ':group':
                        $select->groupBy($value);
                        break;
                    default:
                        //$condition, $value
                        //$select->where()->eq($condition, $value);
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
            $info = $stmt->errorInfo();
            throw new RuntimeException($info['2']);
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
    public static function getDBConRead()
    {
        return Util::getConnection(self::$SimpleObjectConfigNameRead);
    }

    /**
     * @param $name
     *
     * @return bool
     * @noinspection PhpUnused - It's used
     */
    public function __isset($name)
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
    public function save($force = false)
    {
        $builder = new GenericBuilder();
        $data = $this->getDataForSave();
        // Filtering nulls
        $data = array_filter($data, function ($e) {
            return $e !== null;
        });
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
            throw new Exception('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
        return true;
    }

    /**
     * @param bool $applyTransforms
     *
     * @return array|null
     */
    public function getDataForSave($applyTransforms = true)
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
    public function getWriteTransforms($fieldName)
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
    public function isExistInStorage()
    {
        //return !!$this->existInStorage;
        return !empty($this->loadedValues);
    }

    /**
     * @return PDO
     * @noinspection PhpUnused - It's used
     */
    public static function getDBConWrite()
    {
        return Util::getConnection(self::$SimpleObjectConfigNameWrite);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete()
    {
        if (!$this->isExistInStorage()) {
            return false;
        }
        try {
            $builder = new GenericBuilder();
            $delete = $builder->delete();
            $delete->setTable(static::$TableName);
            $delete->where()->eq(static::getIdField(),$this->__get(static::getIdField()));
            $stmt = static::getDBConWrite()->prepare($builder->writeFormatted($delete));
            if(!$stmt->execute($builder->getValues())){
                $errorInfo = $stmt->errorInfo();
                throw new RuntimeException($errorInfo[2]);
            }
        } catch (\Exception $e) {
            throw new Exception('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
        RuntimeCache::getInstance()->drop(static::class, $this->__get(static::getIdField()));
        return true;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function __toArray()
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
    public function rewind()
    {
        reset(static::$propertiesMapping);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        $property = current(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                return $this->__get($property);
            } catch (Exception $e) {
            }
        }
        return false;
    }

    /**
     * @return void
     */
    public function next()
    {
        $property = next(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                $this->__get($property);
                return;
            } catch (Exception $e) {
            }
        }
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $key = $this->key();
        return ($key !== null && $key !== false);
    }

    /**
     * @return int|string|null
     */
    public function key()
    {
        return key(static::$propertiesMapping);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return (static::isPropertyExist($offset) || static::isTableFieldExist($offset));
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (static::isPropertyExist($offset) || static::isTableFieldExist($offset)) {
            try {
                return $this->__get($offset);
            } catch (Exception $e) {
            }
        }
        return false;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        try {
            $this->__set($offset, $value);
        } catch (Exception $e) {
        }
    }

    /**
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
    }

    /**
     * @return int
     */
    public function count()
    {
        return count(static::$propertiesMapping);
    }

}