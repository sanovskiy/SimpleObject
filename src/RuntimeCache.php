<?php

namespace Sanovskiy\SimpleObject;

use Sanovskiy\Interfaces\Patterns\Singleton;

class RuntimeCache implements Singleton
{
    use \Sanovskiy\Traits\Patterns\Singleton;

    protected bool $disabled = false;

    /**
     * @var array
     */
    protected array $cache = [];

    /**
     * @param string $classname
     * @param string $key
     * @param array $data
     */
    public function put(string $classname, string $key, array $data)
    {
        if ($this->disabled) {
            return;
        }
        $this->cache[$classname][$key] = $data;
    }

    /**
     * @param string $classname
     * @param mixed $key
     *
     * @return array|null
     */
    public function get(string $classname, mixed $key): ?array
    {
        if (!$this->disabled && array_key_exists($classname, $this->cache) && array_key_exists($key, $this->cache[$classname])) {
            return $this->cache[$classname][$key];
        }
        return null;
    }

    /**
     * @param string $classname
     * @param mixed $key
     */
    public function drop(string $classname, mixed $key)
    {
        if (array_key_exists($classname, $this->cache) && array_key_exists($key, $this->cache[$classname])) {
            unset($this->cache[$classname][$key]);
        }
    }

    public function clearAll()
    {
        $this->cache = [];
    }

    public function toggle()
    {
        $this->disabled = !$this->disabled;
    }

    public function disable()
    {
        $this->disabled = true;
    }

    public function enable()
    {
        $this->disabled = false;
    }
}