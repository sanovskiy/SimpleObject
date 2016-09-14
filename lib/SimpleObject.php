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
 *
 */

use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\PhpProperty;
use gossi\codegen\model\AbstractPhpMember;

/**
 * Class SimpleObject
 */
class SimpleObject
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
            'charset' => 'utf8'
        ],
        'path_models' => '',
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
     * @var null|PDO
     */
    static private $connections = [
        'default' => null
    ];

    /**
     * @return array
     */
    public static function getRestrictedConfigNames()
    {
        return self::$restrictedConfigNames;
    }

    /**
     * Must be called BEFORE any usage of library
     * @param $options
     * @param string $configName
     * @throws Exception
     */
    public static function init($options, $configName = 'default')
    {
        if (in_array(strtolower($configName), self::$restrictedConfigNames)) {
            throw new Exception('You can\'t use \'' . $configName . '\' as a config name due to ORM limitations');
        }
        self::$settings[$configName] = $options;
    }

    public static function reverseEngineerModels($configName = null)
    {
        if (is_null($configName)) {
            $configNames = self::getConfigNames();
        } else {
            $configNames = [$configName];
        }
        foreach ($configNames as $_configName) {
            self::doReverseEngineerModels($_configName);
        }
    }

    public static function getConfigNames()
    {
        return array_keys(self::$settings);
    }

    protected static function doReverseEngineerModels($configName = 'default')
    {
        if (!class_exists('SimpleConsole')) {
            throw new SimpleObject_Exception('SimpleConsole needed');
        }
        $CC = SimpleConsole::getInstance();
        $dbSettings = self::getSettingsValue('dbcon', $configName);
        if (isset(self::$settings[$configName]['write_connection']) && !is_null(self::$settings[$configName]['write_connection'])) {
            $CC->dropLF();
            $CC->dropText('Ignoring connection ' . $configName . ' marked as read_correction',
                SimpleConsole_Colors::LIGHT_RED);
            return;
        }
        $CC->dropLF();
        $CC->drawLogo(
            [
                'Reverse engineering database ' .
                SimpleConsole_Colors::colorize($dbSettings['database'], SimpleConsole_Colors::LIGHT_GREEN)
            ],
            SimpleConsole_Colors::WHITE
        );

        $CC->dropText('Removing all base models');
        self::wipeBaseModels($configName);

        $sql = 'SHOW TABLES FROM `' . $dbSettings['database'] . '`';
        $stmt = self::getConnection($configName)->prepare($sql);
        $stmt->execute();
        if ($stmt->errorCode() > 0) {
            throw new SimpleObject_Exception($stmt->errorInfo()[2]);
        }
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);
        $generator = new CodeGenerator();

        $CC->dropText('Generating files:');
        foreach ($tables as $tableRow) {

            $tableName = $tableRow[0];
            $CC->indentedEcho('Table ' . SimpleConsole_Colors::colorize($tableName,
                    SimpleConsole_Colors::LIGHT_BLUE) . '... ', SimpleConsole_Colors::GRAY);
            $CCName = SimpleObject_Transform::CCName($tableName);
            $CCConfigName = '';
            if ('default' != $configName) {
                $CCConfigName = SimpleObject_Transform::CCName($configName) . '_';
            }
            $tableInfo = [
                'table_name' => $tableName,
                'file_name' => $CCName . '.php',
                'class_name' => 'Model_' . $CCConfigName . $CCName,
                'base_class_name' => 'Model_' . $CCConfigName . 'Base_' . $CCName,
                'fields' => []
            ];

            $FinalModel = new PhpClass();
            $FinalModel
                ->setName($tableInfo['class_name'])
                ->setParentClassName($tableInfo['base_class_name']);
            $FinalCode = file_get_contents(__DIR__ . '/CodeParts/final_model_head.php') . $generator->generate($FinalModel);
            self::writeModel($tableInfo['file_name'], $FinalCode, false, $configName);
            $CC->cEcho('Final ', SimpleConsole_Colors::GREEN);
            $BaseModel = new PhpClass();
            $BaseModel
                ->setName($tableInfo['base_class_name'])
                ->setParentClassName('SimpleObject_Abstract')
                ->setAbstract(true);

            $writeConfigName = $configName;
            $readConfigName = $configName;

            if (isset(self::$settings[$configName]['read_connection']) && !is_null(self::$settings[$configName]['read_connection'])) {
                $readConfigName = self::$settings[$configName]['read_connection'];
            }

            $BaseModel->setProperty(
                PhpProperty::create('SimpleObjectConfigNameWrite')
                    ->setDefaultValue($writeConfigName)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            $BaseModel->setProperty(
                PhpProperty::create('SimpleObjectConfigNameRead')
                    ->setDefaultValue($readConfigName)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            $BaseModel->setProperty(
                PhpProperty::create('DBTable')
                    ->setDefaultValue($tableInfo['table_name'])
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );

            $TFields = [];
            $Properties = [];
            $field2PropertyTransform = [];
            $property2FieldTransform = [];

            $sql = 'DESCRIBE `' . $tableName . '`';
            $stmt = self::getConnection($configName)->prepare($sql);
            $stmt->execute();
            $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fields as $num => $_row) {
                $TFields[$num] = $_row['Field'];
                $Properties[$num] = SimpleObject_Transform::CCName($_row['Field']);

                switch ($_row['Type']) {
                    default:
                        //$CC->dropText($_row['Type']);
                        break;
                    case 'timestamp':
                    case 'date':
                    case 'datetime':
                        $field2PropertyTransform[$num][] = 'date2time';
                        $property2FieldTransform[$num][] = 'time2date|Y-m-d H:i:s';
                        break;
                    case 'tinyint(1)':
                        $field2PropertyTransform[$num][] = 'digit2boolean';
                        $property2FieldTransform[$num][] = 'boolean2digit';
                        break;
                }
            }
            $BaseModel->setProperty(
                PhpProperty::create('TFields')
                    ->setDefaultValue($TFields)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            $BaseModel->setProperty(
                PhpProperty::create('Properties')
                    ->setDefaultValue($Properties)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            $BaseModel->setProperty(
                PhpProperty::create('field2PropertyTransform')
                    ->setDefaultValue($field2PropertyTransform)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            $BaseModel->setProperty(
                PhpProperty::create('property2FieldTransform')
                    ->setDefaultValue($property2FieldTransform)
                    ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
            );
            foreach ($Properties as $property) {
                $BaseModel->setProperty(
                    PhpProperty::create($property)
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PUBLIC)
                );

            }
            $BaseCode = file_get_contents(__DIR__ . '/CodeParts/base_model_head.php') . $generator->generate($BaseModel);
            self::writeModel($tableInfo['file_name'], $BaseCode, true, $configName);
            $CC->cEcho('Base ', SimpleConsole_Colors::GREEN);
            $CC->dropLF();
        }
        $CC->dropLF();
        $CC->dropText('All done.');
    }

    /**
     * Wipes all autogenerated models
     * @param string $configName
     */
    protected static function wipeBaseModels($configName = 'default')
    {
        if (!file_exists(self::getSettingsValue('path_models', $configName) . '/Base')) {
            mkdir(self::getSettingsValue('path_models', $configName) . '/Base');
        }
        $dir = opendir(self::getSettingsValue('path_models', $configName) . '/Base');
        while ($file = readdir($dir)) {
            if (is_dir(self::getSettingsValue('path_models', $configName) . '/Base/' . $file)) {
                continue;
            }
            unlink(self::getSettingsValue('path_models', $configName) . '/Base/' . $file);
        }
    }

    /**
     * @param string $configName
     * @return SimpleObject_PDO
     */
    public static function getConnection($configName = 'default')
    {
        if (!isset(self::$connections[$configName]) || is_null(self::$connections[$configName])) {
            $dbSettings = self::getSettingsValue('dbcon', $configName);
            $connectString = 'mysql:host=' . $dbSettings['host'] . ';';
            if(isset($dbSettings['socket'])){
                $connectString = 'mysql:unix_socket=' . $dbSettings['socket'] . ';';
            }
            $connectString = $connectString.'dbname=' . $dbSettings['database'] . ';charset=' . $dbSettings['charset'];
            self::$connections[$configName] = new SimpleObject_PDO($connectString, $dbSettings['user'], $dbSettings['password']);
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

    private static function writeModel($filename, $contents, $base = false, $configName = 'default')
    {
        $path = self::getSettingsValue('path_models', $configName) . ($base ? '/Base' : '') . '/' . $filename;
        if (!file_exists($path)) {
            return file_put_contents($path, $contents);
        }
        return false;
    }

    /**
     * @param array $settings
     * @return bool
     */
    private static function validateSettings(array $settings)
    {
        //TODO: implement options validation
        return true;
    }
}



















