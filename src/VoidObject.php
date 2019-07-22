<?php


namespace Sanovskiy\SimpleObject;


/**
 * Class VoidObject
 * Dummy class to placeholder any other class.
 * @package Sanovskiy\SimpleObject
 */
class VoidObject
{
    public static function __callStatic($name, $arguments)
    {
        return true;
    }

    public function __invoke()
    {
        return $this;
    }

    public function __unset($name)
    {
    }

    public function __toString()
    {
        return '';
    }

    public function __call($name, $arguments)
    {
        return $this;
    }

    public function __get($name)
    {
        return $this;
    }

    public function __set($name, $value)
    {
        return $value;
    }

    public function __isset($name)
    {
        return true;
    }
}