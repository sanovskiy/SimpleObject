<?php
/**
 * Created: {13.11.2018}
 *
 * @author    Pavel Terentyev
 * @copyright 2018
 */

namespace Sanovskiy\SimpleObject;

use Sanovskiy\Interfaces\Patterns\Singleton;

/**
 * Class RuntimeCache
 * @package Sanovskiy\SimpleObject
 * @method static RuntimeCache getInstance()
 */
class RuntimeCache implements Singleton
{
    use \Sanovskiy\Traits\Patterns\Singleton;

    /**
     * @var bool
     */
    protected $disabled = false;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param string $classname
     * @param string $key
     * @param array  $data
     */
    public function put($classname, $key, array $data)
    {
        if ($this->disabled){
            return;
        }
        $this->cache[$classname][$key] = $data;
    }

    /**
     * @param string $classname
     * @param        $key
     *
     * @return bool
     */
    public function get($classname, $key)
    {
        if (!$this->disabled && array_key_exists($classname, $this->cache) && array_key_exists($key, $this->cache[$classname])) {
            return $this->cache[$classname][$key];
        }
        return false;
    }

    /**
     * @param string $classname
     * @param        $key
     */
    public function drop($classname, $key)
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