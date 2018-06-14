<?php namespace sanovskiy\SimpleObject;

/**
 * Copyright 2010-2017 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

//use sanovskiy;

/**
 * Class ActiveRecordAbstract
 * @package sanovskiy\SimpleObject
 * @property string $DBTable
 * @property string $SimpleObjectConfigNameRead
 * @property string $SimpleObjectConfigNameWrite
 * @property array $field2PropertyTransform
 * @property array $property2FieldTransform
 */
abstract class ActiveRecordAbstract implements \Iterator, \ArrayAccess, \Countable
{
    protected $SimpleObjectConfigNameRead = 'default';
    protected $SimpleObjectConfigNameWrite = 'default';

    /**
     * @var \sanovskiy\SimpleObject\PDO
     */
    protected $DBConWrite;

    /**
     * @var \sanovskiy\SimpleObject\PDO
     */
    protected $DBConRead;

    /**
     * @var string
     */
    protected static $DBTable = '';

    /**
     * @var string
     */
    public $idSequence = '';

    /**
     * @var array
     */
    protected $TFields = [];

    /**
     * @var array
     */
    protected $Properties = [];

    /**
     * @var array
     */
    protected $field2PropertyTransform = [];

    /**
     * @var array
     */
    protected $property2FieldTransform = [];

    /**
     * @var array
     */
    protected static $runtimeCache = [];

    /**
     * @var int|null
     */
    public $Id = null;

    /**
     * @var bool
     */
    public $notExistInStorage = true;

    /**
     * sanovskiy\SimpleObject\ActiveRecordAbstract constructor.
     * @param int|null $id
     */
    function __construct($id = null)
    {
        $this->init();
        $this->DBConRead = Util::getConnection($this->SimpleObjectConfigNameRead);
        $this->DBConWrite = Util::getConnection($this->SimpleObjectConfigNameWrite);
        if (is_null($id)) {
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

    /**
     * Loads model properties from database OR array where keys are table fields names
     * @param null $data
     * @param bool $applyTransform
     * @return bool
     */
    public function load($data = null, $applyTransform = true)
    {
        $class = get_class($this);

        if (!is_null($data)) {
            $result = $data;
        } else {
            if (
                isset(self::$runtimeCache[$class][$this->{$this->Properties[0]}]) &&
                !empty(self::$runtimeCache[$class][$this->{$this->Properties [0]}])
            ) {
                $result = self::$runtimeCache[$class][$this->{$this->Properties [0]}];
            } else {
                $sql = 'SELECT ' . implode(',',
                        $this->TFields) . ' FROM ' . self::getTableName() . ' WHERE ' . $this->TFields [0] . '=:id LIMIT 1';
                $stmt = $this->DBConRead->prepare($sql);
                $stmt->execute([':id' => $this->{$this->Properties[0]}]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                self::$runtimeCache[$class][$this->{$this->Properties [0]}] = $result;
            }
        }
        if (!is_array($result)) {
            return false;
        }

        foreach ($this->Properties as $PropertyId => $PropertyName) {
            $field = $this->TFields[$PropertyId];
            if (!isset($result[$field])) {
                continue;
            }
            $value = $result[$field];

            if (
                $applyTransform &&
                isset($this->field2PropertyTransform [$PropertyId]) &&
                !is_null($this->field2PropertyTransform [$PropertyId])
            ) {
                $this->$PropertyName = Transform::apply_transform($this->field2PropertyTransform [$PropertyId], $value);
            } else {
                $this->$PropertyName = $value;
            }
        }

        if ($this->Id) {
            $this->notExistInStorage = false;
        }

        return true;

    }

    /**
     * Saves model to storage
     * @return bool
     */
    public function save()
    {
        $bind = [];
        $success = false;
        $data = $this->__toArray(true, true);
        foreach ($data as $field => $value) {
            $bind[':' . $field] = $value;
        }
        if ($this->notExistInStorage) {
            unset($bind[':id']);// = null;
            $fields = $this->TFields;
            unset($fields[0]);

            $sql = 'INSERT INTO ' . self::getTableName() . ' (' . implode(',',
                    $fields) . ') VALUES (' . implode(',', array_keys($bind)) . ')';
            //die($sql);
            $stmt = $this->DBConWrite->prepare($sql);
            $success = $stmt->execute($bind);
            if (!$success && $stmt->errorCode()) {
                $error = $stmt->errorInfo();
                throw new Exception('(' . $error[1] . '): ' . $error[2] . ' table ' . self::getTableName(), (int) $error[0]);
            }
            $this->Id = $this->DBConWrite->lastInsertId(self::getTableName());
            if ($this->Id) {
                $this->notExistInStorage = false;
                $success = true;
            }
        } else {
            $sql = 'UPDATE ' . self::getTableName() . ' SET ';
            $sets = [];
            foreach ($this->TFields as $key => $field) {
                if ($key == 0) {
                    continue;
                }
                $sets[] = '' . $field . '=:' . $field;
            }
            $sql .= implode(',', $sets);
            $sql .= ' WHERE ' . $this->TFields[0] . '=:' . $this->TFields[0];
            $stmt = $this->DBConWrite->prepare($sql);
            $success = $stmt->execute($bind);
            if (!$success && $stmt->errorCode()) {
                $error = $stmt->errorInfo();
                throw new Exception('MySQL(' . $error[1] . '): ' . $error[2] . ' table ' . self::getTableName(), $error[0]);
            }
        }
        $class = get_class($this);
        self::$runtimeCache[$class][$this->{$this->Properties [0]}] = $data;

        return $success;
    }

    /**
     * Loads model properties from array where keys are model properties
     * @param array $data
     * @return bool
     */
    public function populate(array $data = [])
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $Property => $value) {
            if (!in_array($Property, $this->Properties)) {
                continue;
            }
            $this->$Property = $value;
        }

        return true;
    }

    protected function getPropertyId($property)
    {
        return array_search($property, $this->Properties);
    }

    protected function getFieldId($field)
    {
        return array_search($field, $this->TFields);
    }

    public function delete()
    {
        $sql = 'DELETE FROM ' . self::getTableName() . ' WHERE ' . $this->TFields[0] . '=' . ':id';
        $stmt = $this->DBConWrite->prepare($sql);

        return $stmt->execute([':id' => $this->Id]);

    }

    public function hasChanges()
    {
        //TODO: implement db diff check
    }

    public function __toArray($useFieldNames = false, $applyTransform = false)
    {
        $result = [];
        foreach ($this->Properties as $index => $property) {
            $key = $useFieldNames ? $this->TFields[$index] : $property;
            $value = $this->$property;
            if ($applyTransform && isset($this->property2FieldTransform[$index]) && !empty($this->property2FieldTransform[$index])) {
                $value = Transform::apply_transform($this->property2FieldTransform[$index], $value);
            }
            $result[$key] = $value;
        }

        return $result;
    }


    public function getFields()
    {
        return $this->TFields;
    }

    /**
     * Iterator implementation
     */

    /**
     * @return bool
     */
    public function current()
    {
        $property = current($this->Properties);
        if (in_array($property, $this->Properties)) {
            return $this->{$property};
        }

        return false;
    }


    public function next()
    {
        $property = next($this->Properties);
        if ($property) {
            return $this->{$property};
        }

        return false;
    }


    public function key()
    {
        return current($this->Properties);
    }

    public function valid()
    {
        $var = $this->current() !== false;

        return $var;
    }

    public function rewind()
    {
        reset($this->Properties);
    }

    /**
     * ArrayAccess implementation
     */

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return (in_array($offset, $this->Properties) || in_array($offset, $this->TFields));
    }

    public function offsetGet($offset)
    {
        if (in_array($offset, $this->TFields)) {
            $offset = $this->Properties[array_search($offset, $this->TFields)];
        }

        if (in_array($offset, $this->Properties)) {
            $index = array_search($offset, $this->Properties);
            if (isset($this->property2FieldTransform[$index]) && !empty($this->property2FieldTransform[$index])) {
                return Transform::apply_transform($this->property2FieldTransform[$index], $this->{$offset});
            }

            return $this->{$offset};
        }

        return false;
    }

    public function offsetSet($offset, $value)
    {
        if (in_array($offset, $this->Properties)) {
            return $this->$offset = $value;
        }
        if (in_array($offset, $this->TFields)) {
            return $this->{$this->Properties[array_search($offset, $this->TFields)]} = $value;
        }

        return false;
    }

    public function offsetUnset($offset)
    {
        return false;
    }

    /**
     * Countable implementation
     * @return int
     */
    public function count()
    {
        return count($this->Properties);
    }


    /**
     * @param $name
     * @return mixed
     */
    function __get($name)
    {
        switch ($name) {
            case 'noElement':
                return $this->notExistInStorage;
            case 'DBTable':
                return self::getTableName();
            case 'TFields':
                return $this->TFields;
            case 'Properties':
                return $this->Properties;
            case 'IdField':
                return $this->TFields[0];
            case 'SimpleObjectConfigNameRead':
                return $this->SimpleObjectConfigNameRead;
            case 'SimpleObjectConfigNameWrite':
                return $this->SimpleObjectConfigNameWrite;
            case 'property2FieldTransform':
                return $this->property2FieldTransform;
            case 'field2PropertyTransform':
                return $this->field2PropertyTransform;
        }

        return null;
    }


    public static function clearCache($model=null)
    {
        if (is_null($model)){
            self::$runtimeCache = [];
            return;
        }
        if (isset(self::$runtimeCache[$model])){
            unset(self::$runtimeCache[$model]);
        }
        return;
    }

    public static function getClassName()
    {
        return get_called_class();
    }

    public static function getTableName()
    {
        return static::$DBTable;
    }

}