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
use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\PhpProperty;
use gossi\codegen\model\AbstractPhpMember;
use Symfony\Component\Console\Output;

class ModelGenerator
{
    /**
     * @var string
     */
    protected $configName;

    /**
     * @var Output\ConsoleOutput
     */
    protected $output;

    /**
     * ModelGenerator constructor.
     * @param string $configName
     * @param array $config
     */
    public function __construct($configName)
    {
        $this->configName = $configName;
        $this->output = new Output\ConsoleOutput();
    }

    public function run()
    {
        try {
            if (null === Util::getSettingsValue('read_connection',
                    $this->configName) && null !== Util::getSettingsValue('write_connection', $this->configName)) {
                throw new Exception('Ignoring connection ' . $this->configName . ' marked as read_correction');
            }
            $dbSettings = Util::getSettingsValue('dbcon', $this->configName);
            $this->output->write(['Reverse engineering database ' . $dbSettings['database'] . PHP_EOL]);

            $this->prepareDirs();
            switch (strtolower($dbSettings['driver'])) {
                case 'mysql':
                    $sql = 'SHOW TABLES FROM `' . $dbSettings['database'] . '`';
                    $bind = [];
                    $dateformat = 'Y-m-d H:i:s';
                    break;
                case 'sqlsrv':
                case 'odbc':
                    //$sql = 'SELECT CONCAT(TABLE_SCHEMA,\'.\',TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $sql = 'SELECT TABLE_NAME, TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $bind = [':ttype' => 'BASE TABLE'];
                    $dateformat = 'Ymd H:i:s';
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

            $this->output->write(['Generating files:' . PHP_EOL]);
            foreach ($tables as $tableRow) {
                switch (strtolower($dbSettings['driver'])) {
                    case 'mysql':
                        $tableName = $tableRow[0];
                        $tableSchema = '';
                        $CCName = Transform::CCName($tableName);
                        break;
                    case 'sqlsrv':
                    case 'odbc':
                        $tableName = $tableRow[0];
                        $tableSchema = $tableRow[1];
                        $CCName = Transform::CCName($tableRow[0]);
                        break;
                    default:
                        throw new \Exception('Unsupported driver ' . $dbSettings['driver']);
                }

                $this->output->write('Table ' . $tableName . '... ', false);

                $tableInfo = [
                    'table_name'           => $tableName,
                    'file_name'            => $CCName . '.php',
                    'class_namespace'      => Util::getSettingsValue('models_namespace', $this->configName) . 'Logic',
                    'base_class_namespace' => Util::getSettingsValue('models_namespace', $this->configName) . 'Base',
                    'class_name'           => $CCName,
                    'fields'               => []
                ];

                $LogicModel = new PhpClass();
                $LogicModel
                    ->setNamespace($tableInfo['class_namespace'])
                    ->setUseStatements(['Base_' . $tableInfo['class_name'] => $tableInfo['base_class_namespace'] . '\\' . $tableInfo['class_name']])
                    ->setName($tableInfo['class_name'])
                    ->setParentClassName('Base_' . $tableInfo['class_name'])
                    ->setDescription('LogicModel class for ' . $tableInfo['table_name']);

                $LogicCode = $this->getLogicModelHeader() . $generator->generate($LogicModel);
                $this->writeModel($tableInfo['file_name'], $LogicCode, false);

                $this->output->write('[Logic] ', false);

                $BaseModel = new PhpClass();
                $BaseModel
                    ->setNamespace($tableInfo['base_class_namespace'])
                    ->setUseStatements(['sanovskiy\SimpleObject\ActiveRecordAbstract'])
                    ->setName($tableInfo['class_name'])
                    ->setParentClassName('ActiveRecordAbstract')
                    ->setAbstract(true)
                    ->setDescription('Base class for model ' . $tableInfo['class_namespace'] . '\\' . $tableInfo['class_name']);

                $writeConfigName = $this->configName;
                $readConfigName = Util::getSettingsValue('read_connection', $this->configName);

                if (null === $readConfigName) {
                    $readConfigName = $this->configName;
                }

                $BaseModel->setProperty(
                    PhpProperty::create('SimpleObjectConfigNameWrite')
                        ->setValue($writeConfigName)
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                );
                $BaseModel->setProperty(
                    PhpProperty::create('SimpleObjectConfigNameRead')
                        ->setValue($readConfigName)
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                );
                $BaseModel->setProperty(
                    PhpProperty::create('DBTable')
                        ->setValue($tableInfo['table_name'])
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                        ->setStatic(true)
                );

                $TFields = [];
                $Properties = [];
                $Comments = [];
                $field2PropertyTransform = [];
                $property2FieldTransform = [];
                $colVal = [];

                //$sql = 'DESCRIBE `' . $tableName . '`';
                $sql = 'SELECT * FROM information_schema.columns WHERE table_name = :table ';
                $bind = [
                    ':table'    => $tableName,
                    ':database' => $dbSettings['database']
                ];

                switch (strtolower($dbSettings['driver'])) {
                    case 'mysql':
                        $sql .= 'AND table_schema = :database';
                        break;
                    case 'sqlsrv':
                    case 'odbc':
                        $sql .= 'AND table_schema = :schema AND table_catalog = :database';
                        $bind[':schema'] = $tableSchema;
                        break;
                }
                //var_dump($bind);
                //echo $sql;die();
                $stmt = Util::getConnection($this->configName)->prepare($sql);
                $stmt->execute($bind);

                $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
                /*if ($tableName === 'token') {
                    var_dump($fields);
                    die();
                }*/
                foreach ($fields as $num => $_row) {
                    $TFields[$num] = $_row['COLUMN_NAME'];
                    $Properties[$num] = Transform::CCName($_row['COLUMN_NAME']);
                    if (strtolower($dbSettings['driver']) === 'mysql') {
                        if ($_row['COLUMN_TYPE'] == 'int(1)') {
                            $_row['DATA_TYPE'] = 'tinyint';
                        }
                    }
                    switch ($_row['DATA_TYPE']) {
                        case 'timestamp':
                        case 'date':
                        case 'datetime':
                            $field2PropertyTransform[$num][] = 'date2time';
                            $property2FieldTransform[$num][] = 'time2date|'.$dateformat;
                            $colVal[$num] = 'integer';
                            break;
                        case 'tinyint':
                        case 'bit':
                            $field2PropertyTransform[$num][] = 'digit2boolean';
                            $property2FieldTransform[$num][] = 'boolean2digit';
                            $colVal[$num] = 'boolean';
                            break;
                        case 'int':
                            $colVal[$num] = 'integer';
                            break;
                        case 'enum':
                            $colVal[$num] = 'string';
                            break;
                        default:
                            $colVal[$num] = 'string';
                            //$CC->dropText($_row['Type']);
                            break;
                    }
                    if (!empty($_row['COLUMN_COMMENT'])) {
                        $Comments[$num] = $_row['COLUMN_COMMENT'];
                    }
                }
                $BaseModel->setProperty(
                    PhpProperty::create('TFields')
                        ->setExpression(self::arrayToString($TFields))
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                        ->setDescription(['Table fields', '@var array'])
                );
                $BaseModel->setProperty(
                    PhpProperty::create('Properties')
                        ->setExpression(self::arrayToString($Properties))
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                        ->setDescription(['Model properties', '@var array'])
                );
                $BaseModel->setProperty(
                    PhpProperty::create('field2PropertyTransform')
                        ->setExpression(self::arrayToString($field2PropertyTransform))
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                        ->setDescription(['Transformations after data load from DB', '@var array'])
                );
                $BaseModel->setProperty(
                    PhpProperty::create('property2FieldTransform')
                        ->setExpression(self::arrayToString($property2FieldTransform))
                        ->setVisibility(AbstractPhpMember::VISIBILITY_PROTECTED)
                        ->setDescription(['Transformations before data write to DB', '@var array'])
                );
                foreach ($Properties as $num => $property) {
                    $_prop = PhpProperty::create($property)->setVisibility(AbstractPhpMember::VISIBILITY_PUBLIC);
                    $_desc = [];
                    if (isset($Comments[$num])) {
                        $_desc[] = $Comments[$num];
                    }
                    if (isset($colVal[$num])) {
                        $_desc[] = '@val ' . $colVal[$num];
                    }
                    if (count($_desc) > 0) {
                        $_prop->setDescription($_desc);
                    }
                    $BaseModel->setProperty($_prop);

                }
                $BaseCode = $this->getBaseModelHeader() . $generator->generate($BaseModel);
                $this->writeModel($tableInfo['file_name'], $BaseCode, true);
                $this->output->write('[Base]' . PHP_EOL);
            }
            $this->output->write('<info>All done.</info>' . PHP_EOL);

        } catch (\Exception $e) {
            $this->output->write(['<error>' . $e->getMessage() . '</error>' . PHP_EOL]);
        }
    }

    /**
     * @param string $filename
     * @param string $contents
     * @param bool $base
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
     * @return string
     */
    protected function getBaseModelHeader()
    {
        return <<<BASEMODEL
<?php
/**
 * This file created automatically by SimpleObject model generator
 * DO NOT modify this file beacuse it WILL BE DELETED next time you generate models.
 * Use final model instead
 */

BASEMODEL;
    }

    protected function prepareDirs()
    {
        $modelsSuperDir = Util::getSettingsValue('path_models', $this->configName);
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

        $this->output->write(['<info>Removing all base models</info>' . PHP_EOL]);

        $dir = opendir($baseModelsDir);
        while ($file = readdir($dir)) {
            $filePath = $baseModelsDir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                continue;
            }
            unlink($filePath);
        }
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

}