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

/**
 * @method SimpleObject_Filter eq() eq(string $field, mixed $value)
 * @method SimpleObject_Filter gt() gt(string $field, mixed $value)
 * @method SimpleObject_Filter lt() lt(string $field, mixed $value)
 * @method SimpleObject_Filter gteq() gteq(string $field, mixed $value)
 * @method SimpleObject_Filter lteq() lteq(string $field, mixed $value)
 * @method SimpleObject_Filter not() not(string $field, mixed $value)
 * @method SimpleObject_Filter isnull() isnull(string $field)
 * @method SimpleObject_Filter isnotnull() isnotnull(string $field)
 * @method SimpleObject_Filter in() in(string $field, mixed $value)
 * @method SimpleObject_Filter notin() notin(string $field, mixed $value)
 * @method SimpleObject_Filter like() like(string $field, mixed $value)
 * @method SimpleObject_Filter like_in() like_in(string $field, mixed $value)
 * @method SimpleObject_Filter notlike() notlike(string $field, mixed $value)
 */
class SimpleObject_Filter implements Iterator {

	public $_entries = array();
	public $classParams = array();
	public $count = 0;

	function __construct() {
		
	}
	
	/**
	 * 
	 * @return SimpleObject_Filter
	 */
	static public function getNewInstance(){
		return new self();
	}

    public function __call($name, $_) {
		switch (strtolower($name)) {
			default:return false;
			case 'eq': return $this->addfilter($_[0], $_[1], '=');
			case 'gt': return $this->addfilter($_[0], $_[1], '>');
			case 'lt': return $this->addfilter($_[0], $_[1], '<');
			case 'gteq': return $this->addfilter($_[0], $_[1], '>=');
			case 'lteq': return $this->addfilter($_[0], $_[1], '<=');
            case 'neq': case 'not': return $this->addfilter($_[0], $_[1], '!=');
			case 'isnull': return $this->addfilter($_[0], null, 'IS NULL');
			case 'isnotnull': return $this->addfilter($_[0], null, 'IS NOT NULL');
			case 'in': return $this->addfilter($_[0], $_[1], 'IN');
			case 'notin': return $this->addfilter($_[0], $_[1], 'NOT IN');
			case 'like': return $this->addfilter($_[0], $_[1], 'LIKE');
            case 'like_in': return $this->addfilter($_[0], '%'.$_[1].'%', 'LIKE');
			case 'notlike': return $this->addfilter($_[0], $_[1], 'NOT LIKE');
		}
	}

	/**
	 * Adds new filter
	 * @param string $field
	 * @param string $value
	 * @param string $cmp
	 * @return SimpleObject_Filter
	 */
	public function addfilter($field, $value, $cmp="=") {
		$this->_entries[] = array($field, $value, $cmp);
		$this->count = count($this->_entries);
		return $this;
	}

	/**
	 * Returns SQL query
	 * @return string
	 */
	public function __toString() {
		return $this->getQuery();
	}

	public $table = "example";
	public $fields = "*";
	public $distinct = false;

	function isPaged() {
		foreach ($this as $filter) {
			if ("{PAGE}" == strtoupper($filter["cmp"])) {
				return true;
			}
		}
		return false;
	}

	private $andWhere = true;

	/**
	 *
	 * @param type $bool
	 * @return SimpleObject_Filter
	 */
	public function orQuery($bool) {
		$this->andWhere = !$bool;
		return $this;
	}

	/**
	 * Returns assembled query
	 */
	function getQuery($DBCon=null, $countQuery=false) {
		if (is_null($DBCon)) {
			$DBCon = Zend_Registry::get('db');
		}
		$select = $DBCon->select();
		$select->from(array('t'=>$this->table), $countQuery ? 'count(*)' : $this->fields);
		$select->distinct($this->distinct);
		$whereFunc = $this->andWhere ? 'where' : 'orWhere';
		foreach ($this as $filter) {
			switch (strtoupper($filter["cmp"])) {
				default:
					break;
				case "=":
				case "!=":
				case "<":
				case ">":
				case "<=":
				case ">=":
					$select->$whereFunc($filter["key"] . $filter["cmp"] . "?", $filter["value"]);
					break;
				case null:
					$select->$whereFunc($filter["key"]);
					break;
				case "LIKE":
				case "NOT LIKE":
					$select->$whereFunc($filter["key"] . " " . $filter["cmp"] . " ?", $filter["value"]);
					break;
				case "IS NULL":
				case "IS NOT NULL":
					$select->$whereFunc($filter["key"] . " " . $filter["cmp"]);
					break;
				case "IN":
				case "NOT IN":
					if (is_array($filter["value"])) {
						$select->$whereFunc($filter["key"] . " " . $filter["cmp"] . " ('" . implode("','", $filter["value"]) . "')");
					} else {
						$select->$whereFunc($filter["key"] . " " . $filter["cmp"] . " (?)",$filter["value"]);
					}
					break;
				case '{ORDER}':
					if (!$countQuery) {
						$select->order($filter["key"] . " " . $filter["value"]);
					}
					break;
				case "{LIMIT}":
					if (!$countQuery) {
						$select->limit($filter["key"], $filter["value"]);
					}
					break;
				case "{PAGE}":
					if (!$countQuery) {
						$select->limitPage($filter["key"], $filter["value"]);
					}
					break;
                case "":
                    $select->$whereFunc($filter["key"],$filter["value"]);
                    break;
			}
		}
		return (string) $select;
	}

	public function setIDonly($eh=true) {
		$this->IDonly = $eh;
		return $this;
	}

	public function setPage($page, $limitOnPage) {
		$this->addfilter($page, $limitOnPage, '{page}');
		return $this;
	}

	public function setOrder($field='id', $direction='ASC') {
		$this->addfilter($field, $direction, '{order}');
		return $this;
	}

	public function setLimit($limit, $offset=0) {
		$this->addfilter($limit, $offset, '{limit}');
		return $this;
	}

	public function getFilterByCmp($key) {
		foreach ($this as $filter) {
			if ($filter['cmp'] == $key) {
				return $filter;
			}
		}
		return null;
	}

	public function getFilterArray($index) {
		if (!isset($this->_entries[$index]))
			return false;
		$filter = $this->_entries[$index];
		return array(
			"key" => $this->_entries[$index][0],
			"value" => $this->_entries[$index][1],
			"cmp" => $this->_entries[$index][2]
		);
	}

	public function filterKeyExists($key) {
		foreach ($this->_entries as $index => $filter) {
			if ($filter[0] == $key) {
				return $index;
			}
		}
		return false;
	}

	public function rewind() {
		reset($this->_entries);
	}

	public function current() {
		$var = current($this->_entries);
		if ($var == null)
			return $var;
		return array(
			"key" => $var[0],
			"value" => $var[1],
			"cmp" => $var[2]
		);
	}

	public function key() {
		$var = key($this->_entries);
		return $var;
	}

	public function next() {
		$var = next($this->_entries);
		return $var;
	}

	public function valid() {
		$var = $this->current() !== false;
		return $var;
	}

}
