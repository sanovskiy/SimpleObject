<?php namespace sanovskiy\SimpleObject;
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

AutoloadModels::register();

class Util
{
    /**
     * @var array
     */
    protected static $restrictedConfigNames = [
        'base'
    ];

    /**
     * @var array
     */
    static private $default_settings = [
        'dbcon' => [
            'host' => 'localhost',
            'user' => 'root',
            'password' => '',
            'database' => 'simpleobject',
            'charset' => null
        ],
        'path_models' => '',
        'models_namespace' => 'sanovsliy\\SimpleObject\\models\\default\\',
        'read_connection' => null,
        'write_connection' => null
    ];

    /**
     * @var array
     */
    static private $settings = [
        'default' => []
    ];

    /**
     * Database connection
     * @var null|\sanovskiy\SimpleObject\PDO
     */
    static private $connections = [
        'default' => null
    ];

    /**
     * Must be called BEFORE any usage of library
     * @param $options
     * @param string $configName
     * @throws \Exception
     */
    public static function init($options, $configName = 'default')
    {
        if (in_array(strtolower($configName), self::$restrictedConfigNames)) {
            throw new Exception('You can\'t use \'' . $configName . '\' as a config name due to ORM limitations');
        }
        self::$settings[$configName] = $options;
        //AutoloadModels::autolosd();
    }

    /**
     * @return array
     */
    public static function getRestrictedConfigNames()
    {
        return self::$restrictedConfigNames;
    }

    /**
     * @param string $configName
     * @return \sanovskiy\SimpleObject\PDO
     */
    public static function getConnection($configName = 'default')
    {
        if (!isset(self::$connections[$configName]) || is_null(self::$connections[$configName])) {
            $dbSettings = self::getSettingsValue('dbcon', $configName);
            $connectString = 'mysql:host=' . $dbSettings['host'] . ';';
            if (isset($dbSettings['socket'])) {
                $connectString = 'mysql:unix_socket=' . $dbSettings['socket'] . ';';
            }
            $connectString = $connectString . 'dbname=' . $dbSettings['database'] . ($dbSettings['charset'] ? ';charset=' . $dbSettings['charset'] : '');
            self::$connections[$configName] = new PDO($connectString, $dbSettings['user'], $dbSettings['password']);
        }

        return self::$connections[$configName];
    }

    /**
     * @param string $name
     * @param string $configName
     * @return null
     */
    public static function getSettingsValue($name, $configName = 'default')
    {
        if (isset(self::$settings[$configName][$name])) {
            return self::$settings[$configName][$name];
        }
        if (isset(self::$default_settings[$configName][$name])) {
            return self::$default_settings[$configName][$name];
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public static function reverseEngineerModels()
    {
        if ("cli" != php_sapi_name()) {
            throw new Exception('You can call this method only in CLI');
        }

        foreach (self::$settings as $_configName => $_config) {
            $generator = new ModelGenerator($_configName, $_config);
            $generator->run();
        }
    }

    /**
     * @return array
     */
    public static function getConfigNames()
    {
        return array_keys(self::$settings);
    }
}