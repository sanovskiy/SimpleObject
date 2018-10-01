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

class Transform
{
    public static function apply_transform($transform, $value)
    {
        if (is_array($transform)) {
            foreach ($transform as $rule) {
                $value = self::apply_transform($rule, $value);
            }
            return $value;
        }
        $params = explode("|", $transform);
        $func = $params[0];
        if (method_exists(__CLASS__, $func)) {
            $value = call_user_func([__CLASS__, $func], $value, $params);
        }
        return $value;
    }

    /**
     * @param $value
     * @return mixed
     */
    public static function urlCorrect($value)
    {
        $value = preg_replace("/^https?\:\/\/?/", "", $value);
        //$value = preg_replace ( "/^www\./", "", $value );
        return $value;
    }

    /**
     * @param $value
     * @return int|null
     */
    public static function date2time($value)
    {
        if (empty($value)) {
            return null;
        }
        return strtotime($value);
    }

    /**
     * @param $value
     * @param array $params
     * @return bool|null|string
     */
    public static function time2date($value, $params = [])
    {
        if (is_null($value)) {
            return null;
        }
        if (!is_array($params)){
            $params = [$params];
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

    /**
     * @param $value
     * @param array $params
     * @return float
     */
    public static function div($value, $params = array())
    {
        $divider = $params [1];
        if ($divider == 0) {
            return $value;
        }
        return ($value / $divider);
    }

    /**
     * @param $value
     * @param array $params
     * @return mixed
     */
    public static function mult($value, $params = array())
    {
        $multiplier = $params [1];
        return ($value * $multiplier);
    }

    /**
     * @param $value
     * @return bool
     */
    public static function pgbool2bool($value)
    {
        return 't' === strtolower($value);
    }

    /**
     * @param $value
     * @return string
     */
    public static function bool2pgbool($value)
    {
        return $value?'t':'f';
    }

    /**
     * @param $value
     * @return bool
     */
    public static function digit2boolean($value)
    {
        return !(0 >= $value);
    }

    /**
     * @param $value
     * @return string
     */
    public static function digit2textboolean($value)
    {
        return (0 >= $value)?'false':'true';
    }

    /**
     * @param $value
     * @return int
     */
    public static function boolean2digit($value)
    {
        return $value?1:0;
    }

    /**
     * @param $value
     * @return string
     */
    public static function boolean2text($value)
    {
        return $value?'true':'false';
    }

    /**
     * @param $value
     * @return string
     */
    public static function jsonize($value)
    {
        return json_encode($value);
    }

    /**
     * @param $value
     * @return mixed
     */
    public static function unjsonize($value)
    {
        return json_decode($value, true);
    }

    /**
     * @param $value
     * @param $params
     * @return mixed
     */
    public static function SOData($value, $params)
    {
        $className = $params [1];
        if (!class_exists($className) || !($className instanceof ActiveRecordAbstract)) {
            return $value;
        }
        /* @var \Sanovskiy\SimpleObject\ActiveRecordAbstract $instance */
        $instance = new $className($value);
        return $instance->__toArray();
    }

    /**
     * @param $val
     * @return string
     */
    public static function string($val)
    {
        return (string)$val;
    }

    /**
     * @param $val
     * @return int
     */
    public static function intVal($val)
    {
        return (int)$val;
    }

    /**
     * @param $string
     * @param bool $firstCharUpper
     * @return mixed|string
     */
    static public function CCName($string, $firstCharUpper = true)
    {
        $s = strtolower($string);
        $s = str_replace(array('_', '-', '/'), array(' ', ' ', '_'), $s);
        $s = ucwords($s);
        $s = str_replace(' ', '', $s);
        if (!$firstCharUpper) {
            $s = strtolower(substr($s, 0, 1)) . substr($s, 1);
        }
        if (preg_match('/^\d/', $s)) {
            $s = 'd' . $s;
        }
        return $s;
    }

    public static function serialize($data)
    {
        return serialize($data);
    }

    public static function unserialize($data)
    {
        return unserialize($data);
    }
}