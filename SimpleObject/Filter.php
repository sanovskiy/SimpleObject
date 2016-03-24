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

/**
 * SimpleObject Filters class
 *
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
class SimpleObject_Filter implements Iterator
{

    protected $_entries = [];
    protected $andWhere = true;
    protected $distinct = false;
    protected $IDonly = false;

    static public function getNewInstance()
    {
        return new self();
    }

    /**
     * @param $name
     * @param $_
     * @return SimpleObject_Filter
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
     * Adds new filter
     * @param string $field
     * @param string $value
     * @param string $cmp
     * @return SimpleObject_Filter
     */
    public function addfilter($field, $value, $cmp = "=")
    {
        $this->_entries[] = [$field, $value, $cmp];
        $this->count = count($this->_entries);
        return $this;
    }

    /**
     * Returns SQL query
     * @return string
     */
    public function __toString()
    {
        return $this->getQuery();
    }

    /**
     * @param boolean $bool
     * @return SimpleObject_Filter
     */
    public function orQuery($or_instead_and)
    {
        $this->andWhere = !$or_instead_and;
        return $this;
    }

    /**
     * @param bool $countQuery
     * @param string $table
     * @param string $idfield
     * @return array SQL string with bind array
     */
    public function getQuery($countQuery = false, $table = '%%table%%', $idfield = '%%idfield%%')
    {
        if ($countQuery) {
            $columns = 'count(*)';
        } else {
            $columns = $this->IDonly ? $idfield : '*';
        }
        $select = 'SELECT' . ($this->distinct ? ' DISTINCT' : '') . ' ' . $columns . ' FROM ' . $table . ' WHERE ';
        $where = [];
        $bind = [];
        $order = '';
        $limit = '';
        $offset = '';
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
                    $where[] = '`' . $filter["key"] . '`' . $filter["cmp"] . $bind_index;
                    $bind[$bind_index] = $filter['value'];
                    break;
                case "LIKE":
                case "NOT LIKE":
                    $where[] = '`' . $filter["key"] . '` ' . $filter["cmp"] . ' ' . $bind_index;
                    $bind[$bind_index] = $filter['value'];
                    break;
                case "IS NULL":
                case "IS NOT NULL":
                    $where[] = '`' . $filter["key"] . '` ' . $filter["cmp"];
                    break;
                case "IN":
                case "NOT IN":
                    if (is_array($filter["value"])) {
                        $subindexes = [];
                        foreach (array_values($filter["value"]) as $key => $value) {
                            $subindex = $bind_index . '_' . $key;
                            $subindexes[] = $subindex;
                            $bind[$subindex] = $value;
                        }
                        $where[] = '`' . $filter["key"] . '` ' . $filter["cmp"] . ' (' . implode(',',
                                $subindexes) . ')';
                    } else {
                        $where[] = '`' . $filter["key"] . '` ' . $filter["cmp"] . ' (' . $bind_index . ')';
                        $bind[$bind_index] = $filter['value'];
                    }
                    break;
                case '{ORDER}':
                    if (!$countQuery) {
                        $order = ' ORDER BY `' . $filter["key"] . '` ' . ($filter["value"]=='DESC'?'DESC':'ASC');
                    }
                    break;
                case "{LIMIT}":
                    if (!$countQuery) {
                        $limit = ' LIMIT ' . strtoupper($filter["value"]);
                    }
                    break;
                case "{OFFSET}":
                    if (!$countQuery) {
                        $offset = ' OFFSET ' . strtoupper($filter["value"]);
                    }
                    break;
            }
        }
        $sql = $select . implode($this->andWhere ? ' AND ' : ' OR ', $where) . $order . $limit . $offset;
        //echo $sql . PHP_EOL;
        //var_dump($bind);
        //$stmt = $pdo->prepare($sql);
        //$stmt->bindValue()
        return [
            $sql,
            $bind
        ];
    }

    /**
     * @param bool $eh
     * @return SimpleObject_Filter
     */
    public function setIDonly($idOnly = true)
    {
        $this->IDonly = $idOnly;
        return $this;
    }

    /**
     * @param $page
     * @param $limitOnPage
     * @return SimpleObject_Filter
     */
    public function setPage($page, $limitOnPage)
    {
        $offset = $limitOnPage * ($page - 1);
        $this->setLimit($limitOnPage, $offset);
        return $this;
    }

    /**
     * @param bool $distinct
     * @return SimpleObject_Filter
     */
    public function setDistinct($distinct = false)
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * @param string $field
     * @param string $direction
     * @return SimpleObject_Filter
     */
    public function setOrder($field = 'id', $direction = 'ASC')
    {
        $this->addfilter($field, $direction, '{order}');
        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return SimpleObject_Filter
     */
    public function setLimit($limit, $offset = 0)
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
        return array(
            "key" => $var[0],
            "value" => $var[1],
            "cmp" => $var[2]
        );
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