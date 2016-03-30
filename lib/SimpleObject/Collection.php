<?php
/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
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

/**
 * Class SimpleObject_Collection
 */
class SimpleObject_Collection implements Iterator, ArrayAccess, Countable
{
    const ERROR_LOCKED = 'Collection is locked. You can\'t modify elements list';
    const ERROR_CLASS_MISMATCH = 'New object\'s class didn\'t match collection\'s class';
    const ERROR_CLASS_NOT_FOUND = 'Class not found';

    protected $records = [];
    protected $className = null;

    protected $isLocked = false;
    protected $isUnlockable = true;

    /**
     * @param $model_name
     * @param PDOStatement|SimpleObject_Filter|array $data
     * @return SimpleObject_Collection
     * @throws SimpleObject_Exception
     */
    static function factory($model_name, $data)
    {
        if (!class_exists($model_name)) {
            throw new SimpleObject_Exception(self::ERROR_CLASS_NOT_FOUND . ': ' . $model_name);
        }
        $collection = new self;
        if ($data instanceof PDOStatement) {
            while ($row = $data->fetch()) {
                /*  @var SimpleObject_Abstract $object */
                $object = new $model_name;
                $object->load($row);
                $collection->push($object);
            }
        } elseif ($data instanceof SimpleObject_Filter) {
            /*  @var SimpleObject_Abstract $object */
            $object = new $model_name;
            /** @noinspection PhpUndefinedFieldInspection */
            $data->build(false, $object->DBTable, $object->IdField);
            $stmt = SimpleObject::getConnection($object->SimpleObjectConfigName)->prepare($data->getSQL());
            if (!$stmt->execute($data->getBind())) {
                $PDOError = $stmt->errorInfo();
                throw new SimpleObject_Exception('PDOStatement failed to execute query: ' . $stmt->queryString . ' ' . $PDOError);
            }
            return self::factory($model_name, $stmt);
        }
        return $collection;
    }

    /**
     * SimpleObject_Collection constructor.
     * @param array $data Elements array
     * @param null $forceClass
     */
    public function __construct($data = [], $forceClass = null)
    {
        if (!is_null($forceClass)) {
            $this->className = $forceClass;
        }
        if (count($data) > 0) {
            if (empty($this->className)) {
                $this->className = get_class($data[0]);
            }
            foreach ($data as $obj) {
                if ($obj instanceof $this->className || is_subclass_of($obj, $this->className)) {
                    $this->records[] = $obj;
                }
            }
        }
    }

    /**
     *
     * @param boolean $disallowUnlock
     * @return \SimpleObject_Collection
     */
    public function lock($disallowUnlock = false)
    {
        $this->isLocked = true;
        if ($disallowUnlock) {
            $this->isUnlockable = false;
        }
        return $this;
    }

    /**
     *
     * @return \SimpleObject_Collection
     * @throws Exception
     */
    public function unlock()
    {
        if (!$this->isUnlockable) {
            throw new Exception('Collection is not unlockable.');
        }
        $this->isLocked = false;
        return $this;
    }

    /**
     * Sets class name for an empty collection
     * @param string $name
     * @return boolean|\SimpleObject_Collection
     * @throws Exception
     */
    public function setClassName($name)
    {
        if (empty($name)) {
            return false;
        }
        if (count($this->records) > 0) {
            throw new Exception('Collection not empty. You can\'t change classname');
        }
        if ($this->isLocked) {
            throw new Exception('Collection is locked. You can\'t change classname');
        }
        $this->className = $name;
        return $this;
    }

    /**
     * Returns $n-th element
     * @param int $n
     * @return null
     */
    public function getElement($n = 0)
    {
        return isset($this->records[$n]) ? $this->records[$n] : null;
    }

    private $returnedIdList = [];

    /**
     * @return bool
     */
    public function getNextRandomElement()
    {
        $forSelect = array_values(array_diff(array_keys($this->records), $this->returnedIdList));
        if (count($forSelect) > 0) {
            $returnIndex = $forSelect[mt_rand(0, count($forSelect) - 1)];
            $this->returnedIdList[] = $returnIndex;
            return $this->records[$returnIndex];
        }
        return false;
    }

    /**
     *
     */
    public function resetRandom()
    {
        $this->returnedIdList = [];
    }

    /**
     * @param $value
     * @return bool
     * @throws Exception
     */
    public function push($value)
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        if (!is_object($value)) {
            return false;
        }
        if (is_null($this->className) || empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value, $this->className)) {
            throw new Exception(self::ERROR_CLASS_MISMATCH);
        }
        array_push($this->records, $value);
        return true;
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function shift()
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        if (count($this->records) > 0) {
            return array_shift($this->records);
        }
        return null;
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function pop()
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        if (count($this->records) > 0) {
            return array_pop($this->records);
        }
        return null;
    }

    /**
     * @param $value
     * @return bool
     * @throws Exception
     */
    public function unshift($value)
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        if (!is_object($value)) {
            return false;
        }
        if (is_null($this->className) || empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value, $this->className)) {
            throw new Exception(self::ERROR_CLASS_MISMATCH);
        }
        array_unshift($this->records, $value);
        $this->records = array_values($this->records);
        return true;
    }

    /**
     * @param bool $reverse
     * @param string $field
     * @throws Exception
     */
    public function reindexByField($reverse = false, $field = 'Id')
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        $result = [];
        for ($index = 0; $index < count($this->records); $index++) {
            $result[$this->records[$index]->{$field}] = $this->records[$index];
        }
        if ($reverse) {
            krsort($result);
        } else {
            ksort($result);
        }
        $this->records = array_values($result);
    }

    /**
     * @param $method
     * @param array $args
     * @return array
     */
    public function callForEach($method, $args = [])
    {
        $reply = [];
        for ($index = 0; $index < count($this->records); $index++) {
            $reply[$index] = call_user_func_array([$this->records[$index], $method], $args);
        }
        return $reply;
    }

    /**
     * @param $property
     * @param null $value
     */
    public function setForEach($property, $value = null)
    {
        for ($index = 0; $index < count($this->records); $index++) {
            $this->records[$index]->{$property} = $value;
        }
    }

    /**
     * @param $property
     * @return array
     */
    public function getFromEach($property)
    {
        $values = [];
        foreach ($this->records as $index => $element) {
            $values[$index] = $element->{$property};
        }
        //for ($index = 0; $index < count($this->records); $index++) {
        //    $values[$index] = $this->records[$index]->{$property};
        //}
        return $values;
    }

    /**
     * @param $property string
     * @param $value mixed
     * @return SimpleObject_Collection
     * @throws SimpleObject_Exception
     */
    public function getElementsByPropertyValue($property, $value)
    {
        $elements = new self;
        for ($index = 0; $index < count($this->records); $index++) {
            if (!property_exists($this->records[$index], $property)) {
                throw new SimpleObject_Exception('Objects in current set does not have property ' . $property);
            }
            if ($this->records[$index]->{$property} == $value) {
                $elements->push($this->records[$index]);
            }
        }
        return $elements;
    }

    /**
     * @param $method string
     * @param $value mixed
     * @return SimpleObject_Collection
     * @throws SimpleObject_Exception
     */
    public function getElementsByFunctionResult($method, $value)
    {
        $elements = new self;
        for ($index = 0; $index < count($this->records); $index++) {
            if (!method_exists($this->records[$index], $method)) {
                throw new SimpleObject_Exception('Objects in current set does not have method ' . $method);
            }
            if ($this->records[$index]->{$method}() == $value) {
                $elements->push($this->records[$index]);
            }
        }
        return $elements;
    }

    /**
     * @return array
     */
    public function getAllRecords()
    {
        return $this->records;
    }


    /**
     * Iterator implemetation
     */

    /**
     *
     */
    public function rewind()
    {
        reset($this->records);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        $var = current($this->records);
        return $var;
    }

    /**
     * @return mixed
     */
    public function key()
    {
        $var = key($this->records);
        return $var;
    }

    /**
     * @return mixed
     */
    public function next()
    {
        $var = next($this->records);
        return $var;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $var = $this->current() !== false;
        return $var;
    }

    /**
     * Countable implemetation
     */
    public function count()
    {
        return count($this->records);
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
        return isset($this->records[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null
     */
    public function offsetGet($offset)
    {
        if (isset($this->records[$offset])) {
            return $this->records[$offset];
        }
        return null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return bool|mixed|string
     * @throws Exception
     */
    public function offsetSet($offset, $value)
    {
        if ($this->isLocked) {
            throw new Exception(self::ERROR_LOCKED);
        }
        if (!is_object($value) || !is_numeric($offset)) {
            return false;
        }
        if (is_null($this->className) || empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value, $this->className)) {
            throw new Exception(self::ERROR_CLASS_MISMATCH);
        }
        if (is_null($offset)) {
            return $this->records[] = $value;
        } elseif (isset($this->records[$offset]) || $offset == max(array_keys($this->records)) + 1) {
            return $this->records[$offset] = $value;
        }
        return false;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetUnset($offset)
    {
        return false;
    }

}