<?php namespace Sanovskiy\SimpleObject;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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
    protected static array $restrictedConfigNames = [
        'base'
    ];

    /**
     * @var array
     */
    private static array $default_settings = [
        'dbcon' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'user' => 'root',
            'password' => '',
            'database' => 'simpleobject',
            'charset' => null
        ],
        'path_models' => '',
        'models_namespace' => 'Sanovskiy\\SimpleObject\\models\\default\\',
        'base_class_extends' => ActiveRecordAbstract::class,
        'read_connection' => null,
        'write_connection' => null,
        'sql_logfile' => null
    ];

    /**
     * @var array
     */
    private static array $settings = [
        'default' => []
    ];

    /**
     * Database connection
     * @var PDO[]
     */
    private static array $connections = [
        'default' => null
    ];

    /**
     * Must be called BEFORE any usage of library
     * @param $options
     * @param string $configName
     * @throws \Exception
     */
    public static function init($options, string $configName = 'default'): void
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
    public static function getRestrictedConfigNames(): array
    {
        return self::$restrictedConfigNames;
    }

    /**
     * @return void
     */
    public static function resetConnections(): void
    {
        self::$connections = [];
    }

    /**
     * @param string $configName
     * @return PDO
     */
    public static function getConnection(string $configName = 'default'): ?PDO
    {
        if (!isset(self::$connections[$configName]) || null === self::$connections[$configName]) {
            $dbSettings = self::getSettingsValue('dbcon', $configName);
            switch (strtolower($dbSettings['driver'])) {
                case 'sqlsrv':
                    $dsn = $dbSettings['driver'] . ':Server=' . $dbSettings['host'] . ';';
                    if (!empty($dbSettings['failover'])) {
                        $dsn .= 'Failover_Partner=' . $dbSettings['failover'] . ';';
                    }
                    $dsn .= 'Database=' . $dbSettings['database'];
                    break;
                case 'pgsql':
                    $dsn = $dbSettings['driver'] . ':host=' . $dbSettings['host'] . ';';
                    if (!empty($dbSettings['port'])) {
                        $dsn .= 'port=' . $dbSettings['port'] . ';';
                    }
                    $dsn .= 'dbname=' . $dbSettings['database'];
                    break;
                default:
                case 'mysql':
                    $dsn = $dbSettings['driver'] . ':host=' . $dbSettings['host'] . ';';
                    if (isset($dbSettings['socket'])) {
                        $dsn = $dbSettings['driver'] . ':unix_socket=' . $dbSettings['socket'] . ';';
                    }
                    $dsn .= 'dbname=' . $dbSettings['database'] . ($dbSettings['charset'] ? ';charset=' . $dbSettings['charset'] : '');
                    break;
            }
            self::$connections[$configName] = new PDO($dsn, $dbSettings['user'], $dbSettings['password'],$dbSettings['options']??null);
            $logfile = self::getSettingsValue('sql_logfile', $configName);

            if ($logfile && file_exists(dirname($logfile)) && is_writable(dirname($logfile))) {
                try {
                    $logger = new Logger('SO Logger');
                    $logger->pushHandler(new StreamHandler($logfile));
                    self::$connections[$configName]->setLogger($logger);
                } catch (\Exception $e) {

                }
            }
        }

        return self::$connections[$configName];
    }

    /**
     * @param string $name
     * @param string $configName
     * @return null
     */
    public static function getSettingsValue(string $name, $configName = 'default')
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
     * @param bool $silent
     * @throws Exception
     */
    public static function reverseEngineerModels(bool $silent = false)
    {
        if ("cli" !== php_sapi_name()) {
            throw new Exception('You can call this method only in CLI');
        }

        foreach (array_keys(self::$settings) as $_configName) {
            $generator = new ModelGenerator($_configName, $silent);
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