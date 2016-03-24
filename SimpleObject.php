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
 * Class SimpleObject
 */
class SimpleObject
{

    /**
     * @var array
     */
    static private $default_settings = [
        'dbcon' => [
            'host' => 'localhost',
            'user' => 'root',
            'password' => '',
            'database' => 'simpleobject'
        ],
        'path_models' => ''
    ];

    /**
     * @var array
     */
    static private $settings = [];

    /**
     * @param $name
     * @return null
     */
    public static function get_settings_value($name)
    {
        if (isset(self::$settings[$name])) {
            return self::$settings[$name];
        }
        if (isset(self::$default_settings[$name])) {
            return self::$default_settings[$name];
        }
        return null;
    }

    /**
     * Must be called BEFORE any usage of library
     * @param $models_path
     */
    public static function init($options)
    {
        self::$settings = $options;

        spl_autoload_register(['SimpleObject', 'autoload']);
    }

    /**
     * @param array $settings
     * @return bool
     */
    private static function validate_settings(array $settings)
    {
        //TODO: implement options validation
        return true;
    }

    /**
     * @param $classname
     * @return bool
     */
    public static function autoload($classname)
    {
        if (preg_match('/^Model\_/', $classname)) {
            return self::load_model($classname);
        }
        $classpath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $classname) . '.php';
        if (!file_exists($classpath)) {
            return false;
        }
        require $classpath;
        return true;
    }

    /**
     * @param $modelname
     * @return bool
     */
    private static function load_model($modelname)
    {
        $realname = preg_replace('/^Model\_(.+)$/', '$1', $modelname);
        $path = self::$settings['path_models'] . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $realname) . '.php';
        if (!file_exists($path)) {
            return false;
        }
        require $path;
        return true;
    }

    /**
     * Database connection
     * @var null|PDO
     */
    static private $connection = null;

    /**
     * @return SimpleObject_PDO
     */
    public static function getConnection()
    {
        if (is_null(self::$connection))
        {
            $dbSettings = self::get_settings_value('dbcon');
            self::$connection = new SimpleObject_PDO('mysql:host='.$dbSettings['host'].';dbname='.$dbSettings['database'], $dbSettings['user'], $dbSettings['password']);
        }
        return self::$connection;
    }

}