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
 */
abstract class SimpleObject_Abstract implements Iterator, ArrayAccess, Countable
{
    /**
     * @var PDO
     */
    public $DBCon;

    /**
     * @var string
     */
    public $DBTable = '';

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
    public $ID = null;

    /**
     * @var bool
     */
    public $noElement = true;

    /**
     * SimpleObject_Abstract constructor.
     * @param int|null $ID
     */
    function __construct($ID = null)
    {
        $this->init();
        $this->DBCon = SimpleObject::getConnection();
        if (is_null($ID)) {
            $this->ID = null;
            return;
        }

        $this->ID = $ID;
        $this->load();
    }

    /**
     * @return bool
     */
    protected function init()
    {
        return true;
    }

    public function load()
    {
        $class = get_class($this);
        if (
            isset(self::$runtimeCache[$class][$this->{$this->Properties[0]}]) &&
            !empty(self::$runtimeCache[$class][$this->{$this->Properties [0]}])
        ) {
            $result = self::$runtimeCache[$class][$this->{$this->Properties [0]}];
        } else {
            $sql = 'SELECT `' . implode('`,`',
                    $this->TFields) . '` FROM `' . $this->DBTable . '` WHERE `' . $this->TFields [0] . '`=:id LIMIT 1';
            $stmt = $this->DBCon->prepare($sql);
            $stmt->execute([':id' => $this->{$this->Properties[0]}]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            self::$runtimeCache[$class][$this->{$this->Properties [0]}] = $result;
        }

        if (!is_array($result)) {
            return false;
        }
        $this->noElement = false;

        foreach ($this->Properties as $PropertyID => $PropertyName) {
            $field = $this->TFields[$PropertyID];
            $value = $result[$field];

            if (
                isset($this->field2PropertyTransform [$PropertyID]) &&
                !is_null($this->field2PropertyTransform [$PropertyID])
            ) {
                $this->$PropertyName = SimpleObject_Transform::apply_transform($this->field2PropertyTransform [$PropertyID],
                    $value);
            } else {
                $this->$PropertyName = $value;
            }
        }
        return true;

    }

    public function save()
    {

    }

    public function delete()
    {
        $sql = 'DELETE FROM ' . $this->DBTable . ' WHERE ' . $this->TFields[0] . '=' . ':id';
        $stmt = $this->DBCon->prepare($sql);
        return $stmt->execute([':id' => $this->ID]);

    }

    public function has_changes()
    {
        //TODO: implenet db diff check
    }

    public function __toArray($useFieldnames = false)
    {
        $result = [];
        foreach ($this->Properties as $index => $property) {
            $key = $useFieldnames ? $this->TFields[$index] : $property;
            $value = $this->$property;
            if ($useFieldnames && isset($this->property2FieldTransform[$index]) && !empty($this->property2FieldTransform[$index])){
                $value = SimpleObject_Transform::apply_transform($this->property2FieldTransform[$index],$value);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Iterator implemetation
     */

    /**
     * @return bool
     */
    public function current()
    {
        $property = current($this->Properties);
        if ($property) {
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
        if (in_array($offset, $this->Properties)) {
            return $this->$offset;
        }
        if (in_array($offset, $this->TFields)) {
            return $this->{$this->Properties[array_search($offset, $this->TFields)]};
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


}