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

use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\{AbstractPhpMember, PhpClass, PhpProperty};
use League\CLImate\CLImate;

class ModelGenerator
{
    /**
     * @var string
     */
    protected $configName;

    /**
     * @var CLImate
     */
    protected $output;
    /**
     * @var bool
     */
    private $isSilent;

    /**
     * ModelGenerator constructor.
     *
     * @param string $configName
     */
    public function __construct($configName, bool $silent = false)
    {
        $this->configName = $configName;
        $this->isSilent = $silent;
        if (!$this->isSilent) {
            $this->output = new CLImate();
        } else {
            $this->output = new VoidObject();
        }
    }


    public function run(): void
    {
        try {
            if (null === Util::getSettingsValue('read_connection',
                    $this->configName) && null !== Util::getSettingsValue('write_connection', $this->configName)) {
                throw new Exception('Ignoring connection ' . $this->configName . ' marked as read_correction');
            }
            $dbSettings = Util::getSettingsValue('dbcon', $this->configName);
            $this->output->out('Reverse engineering database ' . $dbSettings['database']);

            $this->prepareDirs();
            $bind = [];
            switch (strtolower($dbSettings['driver'])) {
                case 'pgsql':
                    $sql = 'select schemaname,tablename from pg_tables where schemaname=\'public\'';
                    break;
                case 'mysql':
                    $sql = 'SHOW TABLES FROM `' . $dbSettings['database'] . '`';
                    break;
                case 'sqlsrv':
                case 'odbc':
                    //$sql = 'SELECT CONCAT(TABLE_SCHEMA,\'.\',TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $sql = 'SELECT TABLE_NAME, TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $bind = [':ttype' => 'BASE TABLE'];
                    break;
                default:
                    throw new \Exception('Unsupported driver ' . $dbSettings['driver']);
            }

            $stmt = Util::getConnection($this->configName)->prepare($sql);
            $stmt->execute($bind);
            if ($stmt->errorCode() > 0) {
                throw new Exception($stmt->errorInfo()[2]);
            }

            $tables = $stmt->fetchAll(PDO::FETCH_NUM);

            $generator = new CodeGenerator();

            $this->output->out('Generating files:');
            foreach ($tables as $tableRow) {
                switch (strtolower($dbSettings['driver'])) {
                    case 'pgsql':
                        $tableName = $tableRow[1];
                        $tableSchema = $tableRow[0];
                        $CCName = Transform::CCName($tableName);
                        break;
                    case 'mysql':
                        $tableName = $tableRow[0];
                        $tableSchema = '';
                        $CCName = Transform::CCName($tableName);
                        break;
                    case 'sqlsrv':
                    case 'odbc':
                        $tableName = $tableRow[0];
                        $tableSchema = $tableRow[1];
                        $CCName = Transform::CCName($tableName);
                        break;
                    default:
                        throw new \Exception('Unsupported driver ' . $dbSettings['driver']);
                }

                $this->output->inline('Table ' . $tableName . '... ');

                $tableInfo = [
                    'table_name'           => $tableName,
                    'file_name'            => $CCName . '.php',
                    'class_namespace'      => Util::getSettingsValue('models_namespace', $this->configName) . 'Logic',
                    'base_class_namespace' => Util::getSettingsValue('models_namespace', $this->configName) . 'Base',
                    'base_class_extends'   => Util::getSettingsValue('base_class_extends',
                        $this->configName) ?: ActiveRecordAbstract::class,
                    'class_name'           => $CCName,
                    'fields'               => []
                ];


                $LogicModel = new PhpClass();
                $LogicModel
                    ->setNamespace($tableInfo['class_namespace'])
                    ->setUseStatements(['Base_' . $tableInfo['class_name'] => $tableInfo['base_class_namespace'] . '\\' . $tableInfo['class_name']])
                    ->setName($tableInfo['class_name'])
                    ->setParentClassName('Base_' . $tableInfo['class_name'])
                    ->setDescription('LogicModel class for ' . $tableInfo['table_name'])
                ;

                $LogicCode = $this->getLogicModelHeader() . $generator->generate($LogicModel);
                $this->writeModel($tableInfo['file_name'], $LogicCode, false);

                $this->output->inline('[<green>Logic</green>] ');

                $BaseModel = new PhpClass();
                $BaseModel
                    ->setNamespace($tableInfo['base_class_namespace'])
                    ->setUseStatements([$tableInfo['base_class_extends']])
                    ->setName($tableInfo['class_name'])
                    ->setParentClassName(substr(strrchr($tableInfo['base_class_extends'], "\\"), 1))
                    ->setAbstract(true)
                ;

                $writeConfigName = $this->configName;
                $readConfigName = Util::getSettingsValue('read_connection', $this->configName);

                if (null === $readConfigName) {
                    $readConfigName = $this->configName;
                }

                if ($writeConfigName !== 'default') {
                    $BaseModel->setProperty(
                        PhpProperty::create('SimpleObjectConfigNameWrite')
                                   ->setValue($writeConfigName)
                                   ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                                   ->setDescription('Config name for write connection')
                                   ->setStatic(true)
                    );
                }
                if ($readConfigName !== 'default') {
                    $BaseModel->setProperty(
                        PhpProperty::create('SimpleObjectConfigNameRead')
                                   ->setValue($readConfigName)
                                   ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                                   ->setDescription('Config name for read connection')
                                   ->setStatic(true)
                    );
                }
                $BaseModel->setProperty(
                    PhpProperty::create('TableName')
                               ->setValue($tableInfo['table_name'])
                               ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                               ->setStatic(true)
                               ->setDescription('Model database table name')
                );


                $propertiesMapping = [];
                $Comments = [];
                $dataTransformRules = [];
                $colVal = [];

                //$driver = Util::getSettingsValue('driver', $this->configName);

                $sql = 'SELECT * FROM information_schema.columns WHERE table_name = :table';

                $bind = [
                    ':table'    => $tableName,
                    ':database' => $dbSettings['database']
                ];

                switch (strtolower($dbSettings['driver'])) {
                    default:
                    case 'mysql':
                        $sql .= ' AND table_schema = :database';
                        break;
                    case 'sqlsrv':
                    case 'pgsql':
                    case 'odbc':
                        $sql .= ' AND table_schema = :schema AND table_catalog = :database';
                        $bind[':schema'] = $tableSchema;
                        break;
                }

                $stmt = Util::getConnection($this->configName)->prepare($sql);
                $stmt->execute($bind);

                $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
                /*if ($tableName === 'token') {var_dump($fields);die();}*/

                foreach ($fields as $num => $_row) {

                    $_row = array_change_key_case($_row, CASE_UPPER);
                    $colName = $_row['COLUMN_NAME'];
                    $propertiesMapping[$colName] = Transform::CCName($colName);

                    if (strtolower($dbSettings['driver']) === 'mysql') {
                        if ($_row['COLUMN_TYPE'] === 'int(1)') {
                            $_row['DATA_TYPE'] = 'tinyint';
                        }
                    }
                    //echo strtolower($_row['DATA_TYPE']) . PHP_EOL;
                    switch (strtolower($_row['DATA_TYPE'])) {
                        case 'date':
                            $dataTransformRules[$colName] = [
                                'read'  => ['date2time' => []],
                                'write' => ['time2date' => ['format' => 'Y-m-d']]
                            ];
                            $colVal[$colName] = 'integer';
                            break;
                        case 'timestamp':
                        case 'timestamp without time zone':
                        case 'datetime':
                            $dataTransformRules[$colName] = [
                                'read'  => ['date2time' => []],
                                'write' => ['time2date' => ['format' => 'Y-m-d H:i:s']]
                            ];
                            $colVal[$colName] = 'integer';
                            break;
                        case 'tinyint':
                        case 'bit':
                            $dataTransformRules[$colName] = [
                                'read'  => ['digit2boolean' => []],
                                'write' => ['boolean2digit' => []]
                            ];
                            $colVal[$colName] = 'boolean';
                            break;
                        case 'int':
                            $colVal[$colName] = 'integer';
                            break;
                        case 'enum':
                            $colVal[$colName] = 'string';
                            break;
                        case 'json':
                        case 'jsonb':
                            $dataTransformRules[$colName] = [
                                'read'  => ['unjsonize' => []],
                                'write' => ['jsonize' => []]
                            ];
                            $colVal[$colName] = 'array';
                            break;
                        default:
                            $colVal[$colName] = 'string';
                            break;
                    }
                    if (!empty($_row['COLUMN_COMMENT'])) {
                        $Comments[$colName] = $_row['COLUMN_COMMENT'];
                    }
                }
                $BaseModel->setProperty(
                    PhpProperty::create('propertiesMapping')
                               ->setExpression(self::arrayToString($propertiesMapping))
                               ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                               ->setDescription(['Model properties for table field mapping', '@var array'])
                               ->setStatic(true)
                );
                $BaseModel->setProperty(
                    PhpProperty::create('dataTransformRules')
                               ->setExpression(self::arrayToString($dataTransformRules))
                               ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                               ->setDescription(['Transformations for reading and writing', '@var array'])
                               ->setStatic(true)
                );

                $classDescription = [
                    'Base class for model ' . $tableInfo['class_namespace'] . '\\' . $tableInfo['class_name'],
                    ''
                ];
                foreach ($propertiesMapping as $tableField => $property) {
                    $_ = '@property ' . (array_key_exists($tableField,
                            $colVal) ? $colVal[$tableField] : '') . ' $' . $property;

                    if (isset($Comments[$num])) {
                        $_ .= ' ' . $Comments[$tableField];
                    }

                    $classDescription [] = $_;

                }

                $BaseModel
                    ->setDescription(implode(PHP_EOL, $classDescription));;
                $BaseCode = $this->getBaseModelHeader() . $generator->generate($BaseModel);
                $this->writeModel($tableInfo['file_name'], $BaseCode, true);
                $this->output->out('[<blue>Base</blue>]');
            }
            $this->output->green('All done.');

        } catch (\Exception $e) {
            $this->output->error($e->getMessage());
        }
    }

    protected function prepareDirs(): void
    {
        $modelsSuperDir = Util::getSettingsValue('path_models', $this->configName);
        if (empty($modelsSuperDir)) {
            throw new \RuntimeException('path_models is empty');
        }
        $baseModelsDir = $modelsSuperDir . DIRECTORY_SEPARATOR . 'Base';
        $finalModelsDir = $modelsSuperDir . DIRECTORY_SEPARATOR . 'Logic';
        if (!file_exists($baseModelsDir)) {
            if (!mkdir($baseModelsDir, 0755, true) && !is_dir($baseModelsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $baseModelsDir));
            }
        }
        if (!file_exists($finalModelsDir)) {
            if (!mkdir($finalModelsDir, 0755, true) && !is_dir($finalModelsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $finalModelsDir));
            }
        }

        $this->output->bold('Removing all base models');

        $dir = opendir($baseModelsDir);
        while ($file = readdir($dir)) {
            $filePath = $baseModelsDir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                continue;
            }
            unlink($filePath);
        }
    }

    /**
     * @return string
     */
    protected function getLogicModelHeader()
    {
        return <<<LOGICMODEL
<?php
/**
 * This file created automatically by SimpleObject model generator
 * This file will NOT be deleted on next models generation.
 */

LOGICMODEL;
    }

    /**
     * @param string $filename
     * @param string $contents
     * @param bool $base
     *
     * @return bool|int
     */
    protected function writeModel($filename, $contents, $base = false)
    {
        $path = Util::getSettingsValue('path_models',
                $this->configName) . DIRECTORY_SEPARATOR . ($base ? 'Base' : 'Logic') . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($path)) {
            return file_put_contents($path, $contents);
        }
        return false;
    }

    protected static function arrayToString($array, $indent = 0)
    {
        if (!is_array($array)) {
            return $array;
        }
        $str = '[' . PHP_EOL;
        $recs = [];
        foreach ($array as $key => $value) {
            $keyStr = (is_numeric($key) ? $key : '\'' . $key . '\'');
            if (is_array($value)) {
                $recs[] = str_repeat("\t", $indent + 1) . $keyStr . ' => ' . self::arrayToString($value, $indent + 1);
                continue;
            }
            if (is_string($value)) {
                $recs[] = str_repeat("\t", $indent + 1) . $keyStr . ' => \'' . str_replace('\\', '\\\\', $value) . '\'';
                continue;
            }
            $recs[] = str_repeat("\t", $indent) . $keyStr . ' => ' . $value;

        }
        $str .= implode(', ' . PHP_EOL, $recs);
        $str .= PHP_EOL . str_repeat("\t", $indent) . ']';
        return $str;
    }

    /**
     * @return string
     */
    protected function getBaseModelHeader()
    {
        return <<<BASEMODEL
<?php
/**
 * This file created automatically by SimpleObject model generator
 * DO NOT modify this file because it WILL BE DELETED next time you generate models.
 * Use logic model instead
 */

BASEMODEL;
    }

}