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
    public static function getSettingsValue($name)
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
     * @param $options
     */
    public static function init($options)
    {
        self::$settings = $options;
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
        if (is_null(self::$connection)) {
            $dbSettings = self::getSettingsValue('dbcon');
            self::$connection = new SimpleObject_PDO('mysql:host=' . $dbSettings['host'] . ';dbname=' . $dbSettings['database'],
                $dbSettings['user'], $dbSettings['password']);
        }
        return self::$connection;
    }

    public static function reverseEngineerModels()
    {
        if (!class_exists('SimpleConsole')){
            throw new SimpleObject_Exception('SimpleConsole needed');
        }
        $CC = SimpleConsole::getInstance();
        $dbSettings = self::getSettingsValue('dbcon');
        $CC->dropLF();
        $CC->drawLogo(
            [
                'Reverse engineering database '.
                SimpleConsole_Colors::colorize($dbSettings['database'],SimpleConsole_Colors::LIGHT_GREEN)
            ],
            SimpleConsole_Colors::WHITE
        );
        $sql = 'SHOW TABLES FROM `'.$dbSettings['database'].'`';
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        if ($stmt->errorCode()>0){
            throw new SimpleObject_Exception($stmt->errorInfo()[2]);
        }
        while ($tableRow = $stmt->fetch(PDO::FETCH_NUM)) {
            $tableName = $tableRow[0];
            $CCName = SimpleObject_Transform::CCName($tableName);
            $tableInfo = [
                'table_name' => $tableName,
                'file_name' => $CCName,
                'class_name' => 'Model_'.$CCName,
                'base_class_name' => 'Model_Base_' . $CCName,
                'fields' => []
            ];

            $sql = 'DESCRIBE `' . $tableName.'`';
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute();
            while ($_row = $stmt->fetch(PDO::FETCH_ASSOC)){
                $field = [];
                $field['name'] = $_row['Field'];
                $field['field_type'] = $_row['Type'];
                $field['property_name'] = SimpleObject_Transform::CCName($_row['Field']);
                switch ($_row['Type']) {
                    default:
                        //$CC->dropText($_row['Type']);
                        break;
                    case 'timestamp':
                    case 'date':
                    case 'datetime':
                        //$CC->dropText($_row['Type'], SimpleConsole_Colors::LIGHT_BLUE);
                        $field['field2PropertyTransform'] = 'date2time';
                        $field['property2FieldTransform'] = 'time2date';
                        $field['field2ReturnTransform'] = 'time2date|d.m.Y H:i';
                        break;
                    case 'tinyint(1)':
                        //$CC->dropText($_row['Type'], SimpleConsole_Colors::LIGHT_GREEN);
                        $field['field2PropertyTransform'] = 'digit2boolean';
                        $field['property2FieldTransform'] = 'boolean2digit';
                        break;
                }
                $tableInfo['fields'][] = $field;

            }
            $CC->showDump($tableInfo);
        }
    }
}



















