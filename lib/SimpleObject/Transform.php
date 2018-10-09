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
class SimpleObject_Transform {

    static public function callStatic($func, $value, $args = array()) {
        $obj = new self();
        if (method_exists($obj, $func)) {
            return $obj->$func($value, $args);
        }
        return null;
    }

    function urlCorrect($value) {
        $value = preg_replace("/^https?\:\/\/?/", "", $value);
        //$value = preg_replace ( "/^www\./", "", $value );
        return $value;
    }

    function date2time($value) {
        if (empty($value)) {
            return null;
        }
        return strtotime($value);
    }

    function time2date($value, $params = array()) {
        if (is_null($value)) {
            return null;
        }
        if (!isset($params[1])) {
            $format = 'Y-m-d H:i:s';
        } else {
            $format = $params [1];
        }
        /*if (strtotime($value)>0){
            $value = strtotime($value);
        }*/
        if (empty($value)) {
            $value = 0;
        }
        return date($format, $value);
    }

    function time2dateOra($value, $params = array()) {
        if (is_null($value)) {
            return null;
        }
        if (!isset($params[1])) {
            $format = 'DD-MM-YYYY HH24:MI:SS';
        } else {
            $format = $params [1];
        }
        if (empty($value)) {
            $value = 0;
        }
        return new Zend_Db_Expr('to_date(\'' . date('d-m-Y H:i:s', $value) . '\',\'' . $format . '\')');
    }

    function div($value, $params = array()) {
        $divider = $params [1];
        if ($divider == 0) {
            return $value;
        }
        return ($value / $divider);
    }

    function mult($value, $params = array()) {
        $multiplier = $params [1];
        return ($value * $multiplier);
    }

    function pgbool2bool($value) {
        if ('t' == strtolower($value)) {
            return true;
        } else {
            return false;
        }
    }

    function bool2pgbool($value) {
        if ($value) {
            return 't';
        } else {
            return 'f';
        }
    }

    function digit2boolean($value) {
        if (0 >= $value) {
            return false;
        } else {
            return true;
        }
    }

    function digit2textboolean($value) {
        if (0 >= $value) {
            return 'false';
        } else {
            return 'true';
        }
    }

    function boolean2digit($value) {
        if ($value) {
            return 1;
        } else {
            return 0;
        }
    }

    function boolean2text($value) {
        if ($value) {
            return 'true';
        } else {
            return 'false';
        }
    }

    function jsonize($value) {
        return json_encode($value);
    }

    function unjsonize($value) {
        return json_decode($value);
    }

    function SOData($value, $params) {
        $className = $params [1];
        /** @var SimpleObject_Abstract $instance */
        $instance = new $className($value);
        return $instance->toArray();
    }

    function string($val) {
        return (string) $val;
    }

    function intVal($val) {
        return intval($val);
    }

    public function BBParse($value) {
        if (class_exists('ubbParser')) {
            /** @noinspection PhpUndefinedClassInspection */
            $bbParser = new ubbParser();
            /** @noinspection PhpUndefinedMethodInspection */
            return $bbParser->parse($value);
        }
        return false;
    }

    static public function CCName($string, $firstCharUpper = true) {
        $s = strtolower($string);
        $s = str_replace('_', ' ', $s);
        $s = str_replace('-', ' ', $s);
        $s = ucwords($s);
        $s = str_replace(' ', '', $s);
        if (!$firstCharUpper) {
            $s = strtolower(substr($s, 0, 1)) . substr($s, 1);
        }
        return $s;
    }

}