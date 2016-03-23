<?php
/**
 * Copyright (C) 2010-2011 Pavel Terentyev (pavel@terentyev.info)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class SimpleObject_Collection implements Iterator, ArrayAccess, Countable
{

    /**
     * @param string $model_name
     * @param Zend_Db_Statement|SimpleObject_Filter $stmt
     * @return SimpleObject_Collection
     */
    static function factory($model_name, $stmt){
        if ($stmt instanceof Zend_Db_Statement){
            $collection = new self;
            while ($data = $stmt->fetch()){
                /*  @var SimpleObject_Abstract $object */
                $object = new $model_name;
                $object->setData($data);
                $collection->push($object);
            }
        } elseif ($stmt instanceof SimpleObject_Filter) {
            $collection = $model_name::All($stmt);
        }
        return $collection;
    }

    protected $records = array();
    protected $totalCount = 0;
    protected $page = null;
    protected $onPage = null;
    protected $className = NULL;

    protected $isLocked = false;
    protected $isUnlockable = true;

    /**
     * @param array $data Массив элементов
     * @param int $totalCount Общее количество В БАЗЕ! (т.е. без учета фильтров)
     * @param int $page текущая страница
     * @param int $onPage сколько элементов на страницу
     */
    public function __construct ($data=array(), $totalCount = null, $page = null, $onPage = null,$forceClass=null)
    {
        $this->totalCount = !is_null($totalCount)?$totalCount:count($data);
        $this->page = $page;
        $this->onPage = $onPage;
        if (!is_null($forceClass)){
            $this->className = $forceClass;
        }
        if (count($data) > 0) {
            if (empty($this->className)){
                $this->className = get_class($data[0]);
            }
            foreach ($data as $obj) {
                if ($obj instanceof $this->className || is_subclass_of($obj,$this->className)) {
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
    public function lock($disallowUnlock=false){
        $this->isLocked = true;
        if ($disallowUnlock){
            $this->isUnlockable = false;
        }
        return $this;
    }

    /**
     *
     * @return \SimpleObject_Collection
     * @throws Exception
     */
    public function unlock(){
        if (!$this->isUnlockable){
            throw new Exception('Collection is not unlockable.');
        }
        $this->isLocked = false;
        return $this;
    }

    /**
     *
     * @param string $name
     * @return boolean|\SimpleObject_Collection
     * @throws Exception
     */
    public function setClassName ($name)
    {
        if (! is_null($this->className) || empty($name)) {
            return false;
        }
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t change classname');
        }
        $this->className = $name;
        return $this;
    }

    /**
     * Returns $n-th element
     * @param int $num
     * @return mixed
     */
    public function getElement ($n = 0)
    {
        return isset($this->records[$n]) ? $this->records[$n] : null;
    }

    /**
     * @deprecated Use getElement instead
     */
    public function q($n = 0){
        return $this->getElement($n);
    }

    private $returnedIDs = array();
    public function r ()
    {
        $forSelect = array_values(array_diff(array_keys($this->records), $this->returnedIDs));
        if (count($forSelect) > 0) {
            $returnIndex = $forSelect[mt_rand(0, count($forSelect) - 1)];
            $this->returnedIDs[] = $returnIndex;
            return $this->records[$returnIndex];
        }
        return false;
    }

    public function resetRandom(){
        $this->returnedIDs = array();
    }

	/**
	 * Iterator implemetation
	 */
    public function rewind ()
    {
        reset($this->records);
    }

    public function current ()
    {
        $var = current($this->records);
        return $var;
    }

    public function key ()
    {
        $var = key($this->records);
        return $var;
    }

    public function next ()
    {
        $var = next($this->records);
        return $var;
    }

    public function valid ()
    {
        $var = $this->current() !== false;
        return $var;
    }

    public function push ($value)
    {
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t add new elements');
        }
        if (! is_object($value)) {
            return false;
        }
        if (is_null($this->className) || empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value,$this->className)) {
            throw new Exception('New object\'s class didn\'t match collection\'s class');
        }
        array_push($this->records, $value);
        $this->totalCount ++;
    }

    public function shift(){
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t add new elements');
        }
        if (count($this->records)>0){
            return array_shift($this->records);
        }
        return null;
    }

    public function pop(){
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t add new elements');
        }
        if (count($this->records)>0){
            return array_pop($this->records);
        }
        return null;
    }

	public function unshift ($value)
    {
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t add new elements');
        }
        if (! is_object($value)) {
            return false;
        }
        if (is_null($this->className) || empty($this->className)) {
            $this->className = get_class($value);
        }
        if (!($value instanceof $this->className) && !is_subclass_of($value,$this->className)) {
            throw new Exception('New object\'s class didn\'t match collection\'s class');
        }
        array_unshift($this->records,$value);
        $this->totalCount ++;
        $this->records = array_values($this->records);
    }

    public function __call ($method, $args)
    {
        return false;
    }

    public function reindexByID ($sort = false)
    {
        if ($this->isLocked){
            throw new Exception('Collection is locked. You can\'t change it');
        }
        $result = array();
        foreach ($this as $item) {
            $result[$item->ID] = $item;
        }
        if ($sort) {
            ksort($result);
        }
        $this->records = $result;
    }

    public function callForEach ($method, $args = array())
    {
        $reply = array();
        for ($index = 0; $index < count($this->records); $index ++) {
            $reply[$index] = call_user_func_array(array($this->records[$index], $method), $args);
        }
        return $reply;
    }

    public function setForEach ($property, $value = null)
    {
        for ($index = 0; $index < count($this->records); $index ++) {
			$this->records[$index]->$property = $value;
        }
    }

    public function getFromEach ($property)
    {
        $values = array();
        for ($index = 0; $index < count($this->records); $index ++) {
            $values[$index] = $this->records[$index]->$property;
        }
        return $values;
    }

    /**
     * @param $property string
     * @param $value mixed
     * @return SimpleObject_Collection
     * @throws SimpleObject_Exception
     */
    public function getElementsByPropertyValue($property,$value){
        $elements = new SimpleObject_Collection();
        for ($index = 0; $index < count($this->records); $index ++) {
            if (!property_exists($this->records[$index],$property)){
                throw new SimpleObject_Exception('Objects in current set does not have property '.$property);
            }
            if ($this->records[$index]->$property==$value){
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
    public function getElementsByfunctionResult($method,$value){
        $elements = new SimpleObject_Collection();
        for ($index = 0; $index < count($this->records); $index ++) {
            if (!method_exists($this->records[$index],$method)){
                throw new SimpleObject_Exception('Objects in current set does not have method '.$method);
            }
            if ($this->records[$index]->{$method}()==$value){
                $elements->push($this->records[$index]);
            }
        }
        return $elements;
    }

    public function getAll(){
        return $this->records;
    }

    private $pagingVals = array('totalCount', 'page', 'onPage');
    public function __get ($name)
    {
        if (in_array($name, $this->pagingVals)) {
            return $this->$name;
        }
        if (isset($this->records[$name])) {
            return $this->records[$name];
        }
        return null;
    }

    public function __set ($name, $value)
    {
        if (in_array($name, $this->pagingVals) && is_int($value)) {
            $this->$name = $value;
        }
        if ($value instanceof $this->className) {
            $this->records[$name] = $value;
        }
    }

	/**
	 * @return int
	 */
    public function count ()
    {
        return count($this->records);
    }

	public function offsetExists ($offset) {
		return isset($this->records[$offset]);
	}

	public function offsetGet ($offset) {
		if (isset($this->records[$offset])){
			return $this->records[$offset];
		}
		return null;
	}

	public function offsetSet ($offset, $value) {
		if ($value instanceof $this->className){
			if (is_null($offset)) {
				return $this->records[] = $value;
			} elseif (isset($this->records[$offset]) || $offset==count($this->records)+1 ){
				return $this->records[$offset] = $value;
			}
		}
		return false;
	}

	public function offsetUnset ($offset) {
		return false;
	}

}