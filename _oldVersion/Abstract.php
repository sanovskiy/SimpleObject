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
abstract class SimpleObject_Abstract extends SimpleObject_Transform implements Iterator, ArrayAccess, Countable
{

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    public $DBCon;

    /**
     * @var
     */
    public $DBTable;
    /**
     * @var
     */
    public $idSequence;
    /**
     * @var
     */
    public $searchView;
    /**
     * @var array
     */
    protected $auxTables = array();
    /**
     * @var array
     */
    protected $TFields = array();
    /**
     * @var array
     */
    protected $Properties = array();
    /**
     * @var array
     */
    protected $field2PropertyTransform = array();
    /**
     * @var array
     */
    protected $property2FieldTransform = array();
    /**
     * @var array
     */
    protected $field2ReturnTransform = array();
    /**
     * @var null
     */
    public $ID = null;
    /**
     * @var bool
     */
    public $noElement = true;

    /**
     *
     */
    protected function init()
    {
        return;
    }

    /**
     * SimpleObject_Abstract constructor.
     * @param null $ID
     */
    function __construct($ID = null)
    {
        $this->init();
        $this->DBCon = Zend_Registry::get('db');
        if (is_null($ID)) {
            $this->ID = null;
            return;
        }

        $this->ID = $ID;
        $this->setData();
    }

    /**
     * @var null
     */
    static protected $modelPath = null;

    /**
     * @param string $model
     * @return bool
     * @throws SimpleObject_Exception
     */
    static function loadModel($model = '')
    {
        if (!self::$modelPath) {
            self::$modelPath = realpath(dirname(__FILE__) . '/../../models');
        }
        if (!file_exists(self::$modelPath . '/' . $model . '.php')) {
            throw new SimpleObject_Exception('Model ' . $model . ' not found');
        }
        if (!class_exists($model)) {
            require_once(self::$modelPath . '/' . $model . '.php');
        }
        return true;
    }

    /**
     * @return bool
     */
    public function entryExists()
    {
        $select = $this->DBCon->select();
        $select->from($this->DBTable, "count(*)")->where($this->TFields [0] . "=?", $this->ID);
        $rowsCount = $select->query()->fetchColumn(0);
        return $rowsCount == 1 ? true : false;
    }

    /**
     * @var bool
     */
    protected $justCreated = false;

    /**
     *
     */
    public function reload()
    {
        $this->setData();
    }

    /**
     * @param null $force
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save($force = null)
    {
        $bind = array();
        foreach ($this->TFields as $num => $fieldName) {
            if (isset($this->property2FieldTransform [$num]) && !is_null($this->property2FieldTransform [$num])) {
                if (strpos($this->property2FieldTransform [$num], "|") !== false) {
                    $params = explode("|", $this->property2FieldTransform [$num]);
                    $bind [$fieldName] = $this->{$params [0]}($this->{$this->Properties [$num]}, $params);
                } else {
                    $funcName = $this->property2FieldTransform [$num];
                    $this->specCallVal = $this->Properties [$num];
                    $bind [$fieldName] = $this->$funcName($this->{$this->Properties [$num]});
                }
            } else {
                $bind [$fieldName] = $this->{$this->Properties [$num]};
            }
        }
        if (($force == 'insert') || (is_null($bind [$this->TFields [0]]) && $bind [$this->TFields [0]] !== 0)) {//new one
            if (is_null($force)) {
                unset($bind [$this->TFields [0]]);
            }
            if (in_array('creator', $this->TFields) && defined('CURRENT_USER') && !is_null(CURRENT_USER)) {
                $bind['creator'] = CURRENT_USER;
            }
            if (!empty($this->idSequence)) {
                if ($this->DBCon instanceof Zend_Db_Adapter_Oracle) {
                    $this->ID = $bind [$this->TFields [0]] = $this->DBCon->query("select " . $this->idSequence . ".nextval FROM DUAL")->fetchColumn(0);
                } else {
                    $this->ID = $bind [$this->TFields [0]] = $this->DBCon->query("select currval(" . $this->idSequence . "'::text)")->fetchColumn(0);
                }
            }
            $this->DBCon->insert($this->DBTable, $bind);
            $this->justCreated = true;
            if (empty($this->idSequence) && is_null($force)) {
                $this->ID = $this->DBCon->lastInsertId($this->DBTable);
            }
            $this->noElement = false;
        } elseif (($force == 'update') || ($bind [$this->TFields [0]] !== 0)) {
            if (in_array('editor', $this->TFields) && defined('CURRENT_USER') && !is_null(CURRENT_USER)) {
                $bind['editor'] = CURRENT_USER;

            }
            if (in_array('date_edited', $this->TFields)) {
                $bind['date_edited'] = date('Y-m-d H:i:s');
            }
            //print_r($bind);
            //die();
            $this->DBCon->update($this->DBTable, $bind, $this->TFields [0] . "=" . $this->ID);
            $this->noElement = false;
        } else {
            return false;
        }
        return true;
    }

    /**
     *
     */
    function delete()
    {
        $this->DBCon->delete($this->DBTable, $this->TFields [0] . "=" . $this->{$this->Properties [0]});
    }

    /**
     * @param $transformType
     * @param $fieldname
     * @param $value
     * @return bool|mixed
     */
    protected function setTransform($transformType, $fieldname, $value)
    {
        $key = array_search(strtolower($fieldname), $this->TFields);
        if (!is_int($key)) {
            return false;
        }
        switch ($transformType) {
            default:
                return false;
            case 'f2p':
            case 'field2property':
                $this->field2PropertyTransform[$key] = $value;
                break;
            case 'p2f':
            case 'property2field':
                $this->property2FieldTransform[$key] = $value;
                break;
            case 'p2r':
            case 'property2return':
                $this->field2ReturnTransform[$key] = $value;
                break;
        }
        return $key;
    }

    /**
     * @var string
     */
    public $lastSQL = "";
    /**
     * @var array
     */
    protected static $runtimeCache = array();

    /**
     * @param null $sqlReply
     * @return bool
     */
    function setData($sqlReply = null)
    {
        $this->DBCon->setFetchMode(Zend_Db::FETCH_ASSOC);
        if (is_null($sqlReply)) {
            $class = get_class($this);
            if (isset(self::$runtimeCache[$class][$this->{$this->Properties [0]}]) && !empty(self::$runtimeCache[$class][$this->{$this->Properties [0]}])) {
                $result = self::$runtimeCache[$class][$this->{$this->Properties [0]}];
            } else {
                $select = $this->DBCon->select();
                $select->from($this->DBTable, $this->TFields)->where($this->TFields [0] . "=?", $this->{$this->Properties [0]});
                self::$runtimeCache[$class][$this->{$this->Properties [0]}] = $result = $select->query()->fetch();
                $this->lastSQL = $select->__toString();
            }
        } else {
            $result = $sqlReply;
        }
        if (!is_array($result))
            return false;
        $this->noElement = false;

        foreach ($this->Properties as $PropertyID => $PropertyName) {
            $resultItem = $result[$this->TFields[$PropertyID]];
            if (isset($this->field2PropertyTransform [$PropertyID]) && !is_null($this->field2PropertyTransform [$PropertyID])) {
                if (strpos($this->field2PropertyTransform [$PropertyID], "|") !== false) {
                    $params = explode("|", $this->field2PropertyTransform [$PropertyID]);
                    $this->$PropertyName = $this->$params [0]($resultItem, $params);
                } else {
                    $funcName = $this->field2PropertyTransform [$PropertyID];
                    $this->$PropertyName = $this->$funcName($resultItem);
                }
            } else {
                $this->$PropertyName = $resultItem;
            }
        }
        return true;
    }

    /**
     * @return array
     */
    function getData()
    {
        return $this->toArray();
    }

    /**
     * @param string $transformFuncs
     * @return array
     */
    function toArray($transformFuncs = 'field2ReturnTransform')
    {
        $array = array();
        foreach ($this->Properties as $num => $property) {
            if (isset($this->{$transformFuncs}[$num]) && !is_null($this->{$transformFuncs} [$num])) {
                if (strpos($this->{$transformFuncs} [$num], "|") !== false) {
                    $params = explode("|", $this->{$transformFuncs} [$num]);
                    $array [$property] = $this->$params [0]($this->$property, $params);
                } else {
                    $func = $this->{$transformFuncs} [$num];
                    $array [$property] = $this->$func($this->$property);
                }
            } else {
                $array [$property] = $this->$property;
            }
        }
        return $array;
    }

    /**
     * @param $property
     * @return mixed
     */
    function getTransformedValue($property)
    {
        $num = array_search($property, $this->Properties);

        if (isset($this->field2ReturnTransform [$num]) && !is_null($this->field2ReturnTransform [$num])) {
            if (strpos($this->field2ReturnTransform [$num], "|") !== false) {
                $params = explode("|", $this->field2ReturnTransform [$num]);
                return $this->$params [0]($this->$property, $params);
            } else {
                $func = $this->field2ReturnTransform [$num];
                return $this->$func($this->$property);
            }
        } else {
            return $this->$property;
        }

    }

    /**
     * @return null
     */
    function postLoadInit()
    {
        return null;
    }

    /**
     * @var array
     */
    protected static $auxRuntimeCache = array();
    /**
     * @var bool
     */
    public $debugObject = false;
    /**
     * @var array
     */
    public $debugInfo = array();

    /**
     * @param null $viewData
     */
    final function auxInit($viewData = null)
    {
        $class = get_class($this);
        foreach ($this->auxTables as $table => $rules) {
            $TFields = $rules['TFields'];
            if (!is_null($viewData)) {
                $TFields = array_extend($TFields, $rules['viewFieldsReplace']);
                self::$auxRuntimeCache[$class][$table][$this->ID] = $data = $viewData;
            } else {
                if (isset(self::$auxRuntimeCache[$class][$table][$this->ID])) {
                    $data = self::$auxRuntimeCache[$class][$table][$this->ID];
                } else {
                    $select = $this->DBCon->select();
                    $select->from($table, $TFields)->where($rules['auxLinkField'] . '=?', $this->ID);
                    self::$auxRuntimeCache[$class][$table][$this->ID] = $data = $select->query()->fetch(Zend_Db::FETCH_ASSOC);
                }
            }
            foreach ($rules['Properties'] as $PropertyID => $PropertyName) {
                $fieldStr = $data[$TFields[$PropertyID]];
                if (isset($rules['field2PropertyTransform'] [$PropertyID]) && !is_null($rules['field2PropertyTransform'] [$PropertyID])) {
                    if (strpos($rules['field2PropertyTransform'] [$PropertyID], "|") !== false) {
                        $params = explode("|", $rules['field2PropertyTransform'] [$PropertyID]);
                        $this->auxData[$table][$PropertyName] = $this->{$params [0]}($fieldStr, $params);
                    } else {
                        $funcName = $rules['field2PropertyTransform'] [$PropertyID];
                        $this->auxData[$table][$PropertyName] = $this->$funcName($fieldStr);
                    }
                } else {
                    $this->auxData[$table][$PropertyName] = $fieldStr;
                }
            }
        }
    }

    /**
     * @param string $className
     * @param string $idField
     * @param string $table
     * @param SimpleObject_Filter $filters
     * @return SimpleObject_Collection
     */
    static function All(SimpleObject_Filter $filters = null, $className = null, $idField = 'id', $DBCon = NULL)
    {
        if (is_null($className)) {
            $className = get_called_class();
        }
        if (is_null($DBCon)) {
            $DBCon = Zend_Registry::get('db');
        }
        $DBCon->setFetchMode(Zend_Db::FETCH_ASSOC);

        if (is_null($filters)) {
            $filters = new SimpleObject_Filter ();
        }

        if (!($filters instanceof SimpleObject_Filter)) {
            throw new Exception("Array support in SimpleObject filters deprecated from version 2.0");
        }
        if (!class_exists($className)) {
            throw new Exception("No class {$className} found.");
        }

        $objectPrototype = new $className(null);

        if ($filters instanceof SimpleObject_Filter && isset($filters->IDonly) && $filters->IDonly) {
            $filters->fields = $idField;
        } else {
            $filters->fields = $objectPrototype->TFields;
        }

        if (!empty($objectPrototype->searchView)) {
            $filters->table = $objectPrototype->searchView;
            $filters->fields = '*';
        } else {
            $filters->table = $objectPrototype->DBTable;
        }

        $stmt = $DBCon->query($filters->getQuery($DBCon, false));

        $objList = array();
        while ($data = $stmt->fetch(Zend_Db::FETCH_ASSOC)) {
            if ($filters instanceof SimpleObject_Filter && isset($filters->IDonly) && $filters->IDonly) {
                $objList [] = $data [$idField];
            } else {
                $instance = new $className(null);
                $instance->setData($data);

                if (isset($filters->classParams)) {
                    $params = $filters->classParams;
                } else {
                    $params = null;
                }

                $instance->postLoadInit($data, $params);
                $objList [] = $instance;
                unset($instance);
            }
        }
        if (!$filters instanceof SimpleObject_Filter || !isset($filters->IDonly) || !$filters->IDonly) {
            $page = null;
            $onPage = null;
            if ($filters->isPaged()) {
                $totalCount = $DBCon->query($filters->getQuery($DBCon, true))->fetchColumn(0);
                $filterPage = $filters->getFilterByCmp('{page}');
                $page = $filterPage ['key'];
                $onPage = $filterPage ['value'];
            } else {
                $totalCount = count($objList);
            }
            $objList = new SimpleObject_Collection($objList, $totalCount, $page, $onPage);
        }
        return $objList;
    }

    /**
     * @param $attributeName
     * @param $value
     * @param bool $notEmpty
     * @param bool $forceUpdate
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    function updateAttr($attributeName, $value, $notEmpty = false, $forceUpdate = false)
    {
        if ($notEmpty && empty($value))
            return false;
        $index = array_search($attributeName, $this->TFields);
        if (!$index) {
            $index = array_search($attributeName, $this->Properties);
            $attributeName = $this->TFields[$index];
        }

        if ($forceUpdate || !$this->checkAttr($attributeName, $value)) {
            if (isset($this->property2FieldTransform [$index]) && !is_null($this->property2FieldTransform [$index])) {
                if (strpos($this->property2FieldTransform [$index], "|") !== false) {
                    $params = explode("|", $this->property2FieldTransform [$index]);
                    $funcName = $params[0];
                    $bind [$attributeName] = $this->{$funcName}($value, $params);
                } else {
                    $funcName = $this->property2FieldTransform [$index];
                    $bind [$attributeName] = $this->$funcName($value);
                }
            } else {
                $bind [$attributeName] = $value;
            }
            $where = array(
                $this->TFields[0] . '=?' => $this->ID
            );
            //$data = array($attributeName => $value);
            if ($this->DBCon->update($this->DBTable, $bind, $where)) {
                $property = $this->Properties[array_search($attributeName, $this->TFields)];
                $this->$property = $value;
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $attributeName
     * @param $value
     * @return bool
     */
    function checkAttr($attributeName, $value)
    {
        $select = $this->DBCon->select();
        $select->from($this->DBTable, $attributeName)->where($this->TFields [0] . "=?", $this->ID);
        $dbvalue = $select->query()->fetchColumn(0);
        return ($dbvalue == $value ? true : false);
    }

    /**
     * @param type $tableName
     * @return type ArrayAccess realization
     */
    public function offsetExists($offset)
    {
        return (in_array($offset, $this->Properties) || in_array($offset, $this->TFields));
    }

    /**
     * @param mixed $offset
     * @return bool
     */
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

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return bool|mixed
     */
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

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetUnset($offset)
    {
        return false;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->Properties);
    }

    /**
     * Iterator implemetation
     */
    public function rewind()
    {
        reset($this->Properties);
    }

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

    /**
     * @return mixed
     */
    public function key()
    {
        return current($this->Properties);
    }

    /**
     * @return bool
     */
    public function next()
    {
        $property = next($this->Properties);
        if ($property) {
            return $this->{$property};
        }
        return false;
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
     * @param $property
     * @return bool
     */
    public function propertyRegistered($property)
    {
        return in_array($property, $this->Properties);
    }

    /**
     * Devel functions
     */

    /**
     * Checks model's compatibility with corresponding table
     * @param type $tableName
     * @return type
     */
    public function checkTable($tableName = null)
    {
        @define("SILENT", true);
        $this->DBCon->setFetchMode(Zend_Db::FETCH_NUM);
        $query = "DESC " . $this->DBTable;
        $stmt = $this->DBCon->query($query);
        $tableColumns = array();
        while ($data = $stmt->fetch()) {
            $tableColumns[] = $data[0];
        }
        echo "<h1>Testing class `" . get_class($this) . "` compatibiliy with table {$this->DBTable}</h1>";
        echo "<table border=\"1\" style=\"border-collapse:collapse;\" cellpadding=\"5\">";
        echo "<tr><th>Database</th><th>Local</th><th>Property</th><th>f2p</th><th>p2f</th></tr>";
        $tfStrs = array();
        $tfpStrs = array();
        $tpStrs = array();
        for ($ii = 0; $ii < max(array(count($tableColumns), count($this->TFields))); $ii++) {
            $tfStrs[] = "{$ii}=>'{$tableColumns[$ii]}',";
            $cCapitalize = true;
            $pstr = '';
            for ($charPos = 0; $charPos < strlen($tableColumns[$ii]); $charPos++) {
                $char = substr($tableColumns[$ii], $charPos, 1);
                if ($char == '_') {
                    $cCapitalize = true;
                    continue;
                }
                $pstr .= $cCapitalize ? strtoupper($char) : $char;
                $cCapitalize = false;
            }

            $tfpStrs[] = "{$ii}=>'{$pstr}',";
            @$tpStrs[] = "public \${$this->Properties[$ii]};";
            echo "<tr>";
            $localColumn = isset($this->TFields[$ii]) ? $this->TFields[$ii] : "&nbsp;";
            $localProperty = isset($this->Properties[$ii]) ? $this->Properties[$ii] : "&nbsp;";
            $localTransform = isset($this->field2PropertyTransform[$ii]) ? $this->field2PropertyTransform[$ii] : "&nbsp;";
            $dbTransform = isset($this->property2FieldTransform[$ii]) ? $this->property2FieldTransform[$ii] : "&nbsp;";
            $DBColumn = isset($tableColumns[$ii]) ? $tableColumns[$ii] : "&nbsp;";

            $color = "#AAFFAA";
            if (!in_array($DBColumn, $this->TFields)) {
                $color = "#FFAAAA;";
            }
            echo "<td style=\"background-color:{$color};\">{$DBColumn}</td>";

            $color = "#AAFFAA";
            if (!in_array($localColumn, $tableColumns)) {
                $color = "#FFAAAA;";
            }
            echo "<td style=\"background-color:{$color};\">{$localColumn}</td>";

            $color = "#AAFFAA";
            if (!isset($this->$localProperty)) {
                $color = "#FFAAAA;";
            }
            echo "<td style=\"background-color:{$color}\">{$localProperty}</td>";

            if ($localTransform == "&nbsp;") {
                echo "<td style=\"background-color:#FFAAAA;\">{$localTransform}</td>";
            } else {
                echo "<td style=\"background-color:#AAFFAA;\">{$localTransform}</td>";
            }
            if ($dbTransform == "&nbsp;") {
                echo "<td style=\"background-color:#FFAAAA;\">{$dbTransform}</td>";
            } else {
                echo "<td style=\"background-color:#AAFFAA;\">{$dbTransform}</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "<h2 style=\"cursor:pointer;\" onclick=\"obj=document.getElementById('tfStrs_" . get_class($this) . "');if(obj.style.display=='none'){obj.style.display='inline';}else{obj.style.display='none';}\">Class " . get_class($this) . " Tfields array</h3>";
        echo "<p id=\"tfStrs_" . get_class($this) . "\" style=\"display:none;\">" . implode("<br>", $tfStrs) . "</p>";
        echo "<h2 style=\"cursor:pointer;\" onclick=\"obj=document.getElementById('tfpStrs_" . get_class($this) . "');if(obj.style.display=='none'){obj.style.display='inline';}else{obj.style.display='none';}\">Class " . get_class($this) . " Properties array</h3>";
        echo "<p id=\"tfpStrs_" . get_class($this) . "\" style=\"display:none;\">" . implode("<br>", $tfpStrs) . "</p>";
        echo "<h2 style=\"cursor:pointer;\" onclick=\"obj=document.getElementById('tpStrs_" . get_class($this) . "');if(obj.style.display=='none'){obj.style.display='inline';}else{obj.style.display='none';}\">Class " . get_class($this) . " Properties</h3>";
        echo "<p id=\"tpStrs_" . get_class($this) . "\" style=\"display:none;\">" . implode("<br>", $tpStrs) . "</p>";
        return;
    }

    /**
     * Compares SObjects. Outputs HTML table to STDOUT.
     * @param type $objArray
     */
    static public function compare($objArray)
    {
        echo "<table style=\"border-collapse:collapse;margin:5px;\" border=\"1\" cellpadding=\"5\">";
        echo "<tr>";
        foreach ($objArray[0]->Properties as $property) {
            echo "<th>{$property}</th>";
        }
        echo "</tr>";

        foreach ($objArray as $object) {
            echo "<tr>";
            foreach ($objArray[0]->Properties as $property) {
                echo "<td><nobr>{$object->$property}</nobr></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    /**
     *
     * @param array $vaues
     */
    public function fromArray($values)
    {
        foreach ($values as $key => $value) {
            if (in_array($key, $this->Properties)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * @return null|SimpleObject_Abstract
     */
    public function __toString()
    {
        if (in_array($this->Properties, 'Name')) {
            return $this - Name;
        }
        if (in_array($this->Properties, 'Name')) {
            return $this - Name;
        }
        if (in_array($this->Properties, 'Title')) {
            return $this - Name;
        }

        if (in_array($this->Properties, 'Login')) {
            return $this - Login;
        }
        return $this->ID;
    }

}