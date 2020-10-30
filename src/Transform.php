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
    public static function apply_transform(string $transform, $value, $options = [])
    {
        if (method_exists(static::class, $transform)) {
            return call_user_func([static::class, $transform], $value, $options);
        }
        if (preg_match('/^custom_/iu', $transform)) {
            if (array_key_exists('callback', $options) && is_callable($options['callback'])) {
                $callback_options = [$value];

                if (array_key_exists('callback_options', $options) && is_array($options['callback_options'])) {
                    $callback_options = array_merge($callback_options, $options['callback_options']);
                }

                return call_user_func_array($options['callback'], $callback_options);
            }
        }
        return $value;
    }

    /**
     * @param $value
     *
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
     *
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
     * @param       $value
     * @param array $options
     *
     * @return bool|null|string
     */
    public static function time2date(int $value, array $options = [])
    {
        if (is_null($value)) {
            return null;
        }
        if (!is_array($options)) {
            $options = [$options];
        }

        $format = null;
        if (isset($options['format'])) {
            $format = $options['format'];
        } elseif (isset($options[1])) {
            $format = $options[1];
        }

        if ($format === null) {
            $format = 'Y-m-d H:i:s';
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
     * @param integer|float $value
     * @param array         $options
     *
     * @return float
     */
    public static function div($value, array $options = [])
    {
        $divider = $options [1];
        if ($divider == 0) {
            return $value;
        }
        return ($value / $divider);
    }

    /**
     * @param       $value
     * @param array $options
     *
     * @return mixed
     */
    public static function mult($value, $options = [])
    {
        $multiplier = $options[1];
        return ($value * $multiplier);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function pgbool2bool(string $value): bool
    {
        return 't' === strtolower($value);
    }

    /**
     * @param bool $value
     *
     * @return string
     */
    public static function bool2pgbool(bool $value)
    {
        return $value ? 't' : 'f';
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function digit2boolean($value)
    {
        return !(0 >= $value);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function digit2textboolean($value): string
    {
        return (0 >= $value) ? 'false' : 'true';
    }

    /**
     * @param bool $value
     *
     * @return int
     */
    public static function boolean2digit(bool $value)
    {
        return $value ? 1 : 0;
    }

    /**
     * @param bool $value
     *
     * @return string
     */
    public static function boolean2text(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    public static function text2boolean(bool $value): string
    {
        return 'true' === strtolower($value);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function jsonize($value)
    {
        return json_encode($value);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    public static function unjsonize(?string $value=null)
    {
        if ($value===null){
            return 'null';
        }
        return json_decode($value, true);
    }

    /**
     * @param $val
     *
     * @return string
     */
    public static function string($val)
    {
        return (string)$val;
    }

    /**
     * @param $val
     *
     * @return int
     */
    public static function intVal($val)
    {
        return (int)$val;
    }

    /**
     * @param string $string
     * @param array  $options
     *
     * @return mixed|string
     */
    static public function CCName(string $string, $options = [])
    {
        $firstCharUpper = isset($options[0]) ? !!$options[0] : true;
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

    /**
     * @param $data
     *
     * @return string
     */
    public static function serialize($data)
    {
        return serialize($data);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public static function unserialize($data)
    {
        return unserialize($data);
    }
}