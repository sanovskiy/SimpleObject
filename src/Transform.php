<?php namespace Sanovskiy\SimpleObject;
use Sanovskiy\Utility\NamingStyle;
use Sanovskiy\Utility\Strings;

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
     * @param string $value
     *
     * @return string|array|null
     * @noinspection PhpUnused
     */
    public static function urlCorrect(string $value): string|array|null
    {
        return preg_replace("/^https?:\/\/?/", "", $value);
    }

    /**
     * @param $value
     *
     * @return int|null
     */
    public static function date2time($value): ?int
    {
        if (empty($value)) {
            return null;
        }
        return strtotime($value);
    }

    /**
     * @param int|null $value
     * @param array $options
     *
     * @return bool|null|string
     */
    public static function time2date(?int $value, array $options = []): bool|string|null
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
     * @param array $options
     *
     * @return float
     */
    public static function div(int|float $value, array $options = []): float|int
    {
        $divider = $options [1];
        if ($divider == 0) {
            return $value;
        }
        return ($value / $divider);
    }

    /**
     * @param int|float $value
     * @param array $options
     *
     * @return int|float
     */
    public static function mult(int|float $value, array $options = []): int|float
    {
        $multiplier = $options[1];
        return ($value * $multiplier);
    }

    /**
     * @param string $value
     *
     * @return bool
     * @noinspection PhpUnused
     */
    public static function pgbool2bool(string $value): bool
    {
        return 't' === strtolower($value);
    }

    /**
     * @param bool $value
     *
     * @return string
     * @noinspection PhpUnused
     */
    public static function bool2pgbool(bool $value): string
    {
        return $value ? 't' : 'f';
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function digit2boolean($value): bool
    {
        return !(0 >= $value);
    }

    /**
     * @param $value
     *
     * @return string
     * @noinspection PhpUnused
     */
    public static function digit2textboolean($value): string
    {
        return (0 >= $value) ? 'false' : 'true';
    }

    /**
     * @param bool $value
     *
     * @return int
     * @noinspection PhpUnused
     */
    public static function boolean2digit(bool $value): int
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
     * @noinspection PhpUnused
     */
    public static function jsonize($value): string
    {
        return json_encode($value);
    }

    /**
     * @param string|null $value
     *
     * @return mixed
     */
    public static function unjsonize(?string $value=null): mixed
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
    public static function string($val): string
    {
        return (string)$val;
    }

    /**
     * @param $val
     *
     * @return int
     */
    public static function intVal($val): int
    {
        return (int)$val;
    }

    /**
     * @param string $string
     * @param array $options
     *
     * @return string
     * @deprecated Use Sanovskiy\Utility\NamingStyle\NamingStyle::toCamelCase()
     */
    static public function CCName(string $string, array $options = []): string
    {
        return NamingStyle::toCamelCase($string,!isset($options[0]) || !!$options[0]);
    }

    /**
     * @param $data
     *
     * @return string
     */
    public static function serialize($data): string
    {
        return serialize($data);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public static function unserialize($data): mixed
    {
        return unserialize($data);
    }
}