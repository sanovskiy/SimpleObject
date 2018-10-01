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

/**
 * Filters class
 * @package Sanovskiy\SimpleObject
 * @method Filter eq() eq(string $field, mixed $value)
 * @method Filter gt() gt(string $field, mixed $value)
 * @method Filter lt() lt(string $field, mixed $value)
 * @method Filter gteq() gteq(string $field, mixed $value)
 * @method Filter lteq() lteq(string $field, mixed $value)
 * @method Filter neq() neq(string $field, mixed $value)
 * @method Filter not() not(string $field, mixed $value)
 * @method Filter isnull() isnull(string $field)
 * @method Filter isnotnull() isnotnull(string $field)
 * @method Filter in() in(string $field, mixed $value)
 * @method Filter notin() notin(string $field, mixed $value)
 * @method Filter like() like(string $field, mixed $value)
 * @method Filter like_in() like_in(string $field, mixed $value)
 * @method Filter notlike() notlike(string $field, mixed $value)
 */
class Filter implements \Iterator
{

    protected $_entries = [];
    protected $andWhere = true;
    protected $distinct = false;
    protected $idOnly = false;
    protected $isPaged = false;

    /**
     * @return boolean
     */
    public function isPaged()
    {
        return $this->isPaged;
    }

    static public function getNewInstance()
    {
        return new self();
    }

    /**
     * @param $name
     * @param $_
     * @return Filter
     */
    public function __call($name, $_)
    {
        switch (strtolower($name)) {
            default:
                return $this;
            case 'eq':
                return $this->addfilter($_[0], $_[1], '=');
            case 'gt':
                return $this->addfilter($_[0], $_[1], '>');
            case 'lt':
                return $this->addfilter($_[0], $_[1], '<');
            case 'gteq':
                return $this->addfilter($_[0], $_[1], '>=');
            case 'lteq':
                return $this->addfilter($_[0], $_[1], '<=');
            case 'neq':
            case 'not':
                return $this->addfilter($_[0], $_[1], '!=');
            case 'isnull':
                return $this->addfilter($_[0], null, 'IS NULL');
            case 'isnotnull':
                return $this->addfilter($_[0], null, 'IS NOT NULL');
            case 'in':
                return $this->addfilter($_[0], $_[1], 'IN');
            case 'notin':
                return $this->addfilter($_[0], $_[1], 'NOT IN');
            case 'like':
                return $this->addfilter($_[0], $_[1], 'LIKE');
            case 'notlike':
                return $this->addfilter($_[0], $_[1], 'NOT LIKE');
        }
    }

    /**
     * @return int|bool
     */
    public function getLimit()
    {
        foreach ($this->_entries as $filter) {
            if ('{limit}' == $filter[2]) {
                return $filter[0];
            }
        }

        return false;
    }

    /**
     * @return int|bool
     */
    public function getOffset()
    {
        foreach ($this->_entries as $filter) {
            if ('{offset}' == $filter[2]) {
                return $filter[0];
            }
        }

        return false;
    }

    /**
     * Adds new filter
     * @param string $field
     * @param string $value
     * @param string $cmp
     * @return Filter
     */
    public function addfilter($field, $value, $cmp = "=")
    {
        $this->_entries[] = [$field, $value, $cmp];

        return $this;
    }

    /**
     * @param boolean $or_instead_and
     * @return Filter
     */
    public function orQuery($or_instead_and)
    {
        $this->andWhere = !$or_instead_and;

        return $this;
    }

    protected $sql = '';
    protected $bind = null;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getSQL();
    }

    /**
     * @return array
     */
    public function __toArray()
    {
        return [
            'sql'  => $this->getSQL(),
            'bind' => $this->getBind(),
        ];
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        if (is_null($this->sql)) {
            $this->build();
        }

        return $this->sql;
    }

    /**
     * @return array
     */
    public function getBind()
    {
        if (is_null($this->bind)) {
            $this->build();
        }

        return $this->bind;
    }

    /**
     * @param bool   $countQuery
     * @param string $table
     * @param string $idfield
     *
     * @return string SQL string with bind array
     */
    public function build($countQuery = false, $table = '%%table%%', $idfield = '%%idfield%%')
    {
        if ($countQuery) {
            $columns = 'count(*)';
        } else {
            $columns = $this->idOnly ? $idfield : '*';
        }
        $select = 'SELECT' . ($this->distinct ? ' DISTINCT' : '') . ' ' . $columns . ' FROM ' . $table;
        $where = [];
        $bind = [];
        $limit = '';
        $offset = '';
        $order = [];
        foreach ($this as $index => $filter) {
            $bind_index = ':param' . $index;
            switch (strtoupper($filter["cmp"])) {
                default:
                    break;
                case "=":
                case "!=":
                case "<":
                case ">":
                case "<=":
                case ">=":
                    $where[] =  $filter["key"] . ' ' . $filter["cmp"] . $bind_index;
                    $bind[ $bind_index ] = $filter['value'];
                    break;
                case "LIKE":
                case "NOT LIKE":
                    $where[] = ' ' . $filter["key"] . ' ' . $filter["cmp"] . ' ' . $bind_index;
                    $bind[ $bind_index ] = $filter['value'];
                    break;
                case "IS NULL":
                case "IS NOT NULL":
                    $where[] = $filter["key"] . ' ' . $filter["cmp"];
                    break;
                case "IN":
                case "NOT IN":
                    if (is_array($filter["value"])) {
                        $subindexes = [];
                        foreach (array_values($filter["value"]) as $key => $value) {
                            $subindex = $bind_index . '_' . $key;
                            $subindexes[] = $subindex;
                            $bind[ $subindex ] = $value;
                        }
                        $where[] = $filter["key"] . ' ' . $filter["cmp"] . ' (' . implode(',',
                                $subindexes) . ')';
                    } else {
                        $where[] = $filter["key"] . ' ' . $filter["cmp"] . ' (' . $bind_index . ')';
                        $bind[ $bind_index ] = $filter['value'];
                    }
                    break;
                case '{ORDER}':
                    if (!$countQuery) {
                        $order[] = $filter["key"] . ' ' . ($filter["value"] == 'DESC' ? 'DESC' : 'ASC');
                    }
                    break;
                case '{ORDEREXPR}':
                    if (!$countQuery) {
                        $order[] = $filter["key"] . ' ' . ($filter["value"] == 'DESC' ? 'DESC' : 'ASC');
                    }
                    break;
                case "{LIMIT}":
                    if (!$countQuery) {
                        $limit = ' LIMIT ' . strtoupper($filter["key"]);
                    }
                    break;
                case "{OFFSET}":
                    if (!$countQuery) {
                        $offset = ' OFFSET ' . strtoupper($filter["key"]);
                    }
                    break;
            }
        }
        $whereStr = '';
        if (count($where) > 0) {
            $whereStr = (' WHERE ' . implode($this->andWhere ? ' AND ' : ' OR ', $where));
        }
        $orderStr = '';
        if (count($order) > 0) {
            $orderStr = ' ORDER BY ' . implode(',', $order);
        }
        $this->sql = $select . $whereStr . $orderStr . $limit . $offset;
        $this->bind = $bind;
        return $this->sql;
    }

    /**
     * @param bool $idOnly
     * @return Filter
     */
    public function setIdOnly($idOnly = true)
    {
        $this->idOnly = $idOnly;

        return $this;
    }

    /**
     * @param $page
     * @param $limitOnPage
     * @return Filter
     */
    public function page($page, $limitOnPage)
    {
        $this->isPaged = true;
        $offset = $limitOnPage * ($page - 1);
        $this->limit($limitOnPage, $offset);

        return $this;
    }

    /**
     * @param bool $distinct
     * @return Filter
     */
    public function distinct($distinct = false)
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * @param string $field
     * @param string $direction
     * @return Filter
     */
    public function order($field = 'id', $direction = 'ASC')
    {
        $this->addfilter($field, $direction, '{order}');

        return $this;
    }

    /**
     * @param        $expression
     * @param string $direction
     *
     * @return Filter
     */
    public function orderExpresstion($expression, $direction = 'ASC')
    {
        $this->addfilter($expression, $direction, '{orderexpr}');

        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return Filter
     */
    public function limit($limit, $offset = 0)
    {
        $this->addfilter($limit, null, '{limit}');
        if ($offset > 0) {
            $this->addfilter($offset, null, '{offset}');
        }

        return $this;
    }

    /**
     * @param $key
     * @return bool
     */
    public function filterKeyExists($key)
    {
        $key = strtoupper($key);
        foreach ($this->_entries as $index => $filter) {
            if (strtoupper($filter[0]) == $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Iterator implementation
     */

    /**
     *
     */
    public function rewind()
    {
        reset($this->_entries);
    }

    /**
     * @return array|mixed
     */
    public function current()
    {
        $var = current($this->_entries);
        if ($var == null) {
            return $var;
        }

        return [
            "key"   => $var[0],
            "value" => $var[1],
            "cmp"   => $var[2],
        ];
    }

    /**
     * @return mixed
     */
    public function key()
    {
        $var = key($this->_entries);

        return $var;
    }

    /**
     * @return mixed
     */
    public function next()
    {
        $var = next($this->_entries);

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

}