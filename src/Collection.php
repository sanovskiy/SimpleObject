<?php namespace Sanovskiy\SimpleObject;

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

use Sanovskiy\Traits\{ArrayAccess, Countable, Iterator};

/**
 * Class Collection
 * @package Sanovskiy\SimpleObject
 */
class Collection implements \Iterator, \ArrayAccess, \Countable
{
    use Iterator, ArrayAccess, Countable;

    const ERROR_LOCKED          = 'Collection is locked. You can\'t modify elements list';
    const ERROR_CLASS_MISMATCH  = 'New object\'s class didn\'t match collection\'s class';
    const ERROR_CLASS_NOT_FOUND = 'Class not found';

    protected $records = [];
    protected $className = null;

    protected $isLocked = false;
    protected $isUnlockable = true;

    //<editor-fold desc="Collection interface">

    /**
     * SimpleObject_Collection constructor.
     *
     * @param array  $data Elements array
     * @param string $forceClass
     */
    public function __construct(array $data = [], string $forceClass = null)
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
     * @param bool $disallowUnlock
     *
     * @return Collection
     */
    public function lock(bool $disallowUnlock = false): Collection
    {
        $this->isLocked = true;
        if ($disallowUnlock) {
            $this->isUnlockable = false;
        }
        return $this;
    }

    /**
     *
     * @return Collection
     * @throws Exception
     */
    public function unlock(): Collection
    {
        if (!$this->isUnlockable) {
            throw new Exception('Collection is not unlockable.');
        }
        $this->isLocked = false;
        return $this;
    }

    /**
     * Sets class name for an empty collection
     *
     * @param string $name
     *
     * @return Collection
     * @throws Exception
     */
    public function setClassName(string $name): Collection
    {
        if (empty($name)) {
            return $this;
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
     *
     * @param int $n
     *
     * @return null
     */
    public function getElement(int $n = 0)
    {
        return isset($this->records[$n]) ? $this->records[$n] : null;
    }
    //</editor-fold>

    //<editor-fold desc="Random access">
    private $returnedIdList = [];

    /**
     * @return mixed
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
     * @return void
     */
    public function resetRandom()
    {
        $this->returnedIdList = [];
    }
    //</editor-fold>

    //<editor-fold desc="Array behavior">
    /**
     * @param $value
     *
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
        if (!($value instanceof $this->className)) {
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
     *
     * @return bool
     * @throws Exception
     */
    public function unshift($value): bool
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
    //</editor-fold>

    //<editor-fold desc="Custom items actions">
    /**
     * @param bool   $reverse
     * @param string $field
     *
     * @return void
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
     * @param string $method
     * @param array  $args
     *
     * @return array
     */
    public function callForEach(string $method, array $args = []): array
    {
        $reply = [];
        for ($index = 0; $index < count($this->records); $index++) {
            $reply[$index] = call_user_func_array([$this->records[$index], $method], $args);
        }
        return $reply;
    }

    /**
     * @param      $property
     * @param null $value
     *
     * @return void
     */
    public function setForEach($property, $value = null)
    {
        for ($index = 0; $index < count($this->records); $index++) {
            $this->records[$index]->{$property} = $value;
        }
    }

    /**
     * @param string|array $property
     *
     * @return array
     */
    public function getFromEach($property): array
    {
        $values = [];
        foreach ($this->records as $index => $element) {
            if (!is_array($property)) {
                $values[$index] = $element->{$property};
            } else {
                $_ = [];
                foreach ($property as $prop) {
                    $_[$prop] = $element->{$prop};
                }
                $values[$index] = $_;
            }
        }
        //for ($index = 0; $index < count($this->records); $index++) {
        //    $values[$index] = $this->records[$index]->{$property};
        //}
        return $values;
    }

    /**
     * @param string $property
     * @param mixed  $value
     *
     * @return Collection
     * @throws Exception
     */
    public function getElementsByPropertyValue(string $property, $value): Collection
    {
        $elements = new self;
        for ($index = 0; $index < count($this->records); $index++) {
            if (!property_exists($this->records[$index], $property)) {
                throw new Exception('Objects in current set does not have property ' . $property);
            }
            if ($this->records[$index]->{$property} == $value) {
                $elements->push($this->records[$index]);
            }
        }
        return $elements;
    }

    /**
     * @param string $method
     * @param mixed  $value
     *
     * @return Collection
     * @throws Exception
     */
    public function getElementsByFunctionResult(string $method, $value): Collection
    {
        $elements = new self;
        for ($index = 0; $index < count($this->records); $index++) {
            if (!method_exists($this->records[$index], $method)) {
                throw new Exception('Objects in current set does not have method ' . $method);
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
    public function getAllRecords(): array
    {
        return $this->records;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    function __get($name)
    {
        $object = new $this->className;
        if (in_array($name, $object->Properties)) {
            $result = $this->getFromEach($name);
            return implode(', ', $result);
        }
        return null;
    }
    //</editor-fold>


}