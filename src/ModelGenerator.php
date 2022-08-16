<?php
namespace Sanovskiy\SimpleObject;


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

use League\CLImate\CLImate;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use RuntimeException;

class ModelGenerator
{
    const PHP_HEADER = '<?PHP' . PHP_EOL;
    /**
     * @var string
     */
    protected string $configName;
    /**
     * @var CLImate|VoidObject
     */
    protected VoidObject|CLImate $output;
    /**
     * @var bool
     */
    private bool $isSilent;

    /**
     * ModelGenerator constructor.
     *
     * @param string $configName
     * @param bool $silent
     */
    public function __construct(string $configName, bool $silent = false)
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
            if (null === Util::getSettingsValue(
                    'read_connection',
                    $this->configName
                ) && null !== Util::getSettingsValue('write_connection', $this->configName)) {
                throw new Exception('Ignoring connection ' . $this->configName . ' marked as read_correction');
            }
            $dbSettings = Util::getSettingsValue('dbcon', $this->configName);
            $this->output->out(sprintf("Reverse engineering database %s", $dbSettings['database']));

            $this->prepareDirs();
            $bind = [];
            switch (strtolower($dbSettings['driver'])) {
                case 'pgsql':
                    $sql = 'select schemaname,tablename from pg_tables where schemaname=\'public\'';
                    break;
                case 'mysql':
                    $sql = sprintf("SHOW TABLES FROM `%s`", $dbSettings['database']);
                    break;
                case 'sqlsrv':
                case 'odbc':
                    //$sql = 'SELECT CONCAT(TABLE_SCHEMA,\'.\',TABLE_NAME) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $sql = 'SELECT TABLE_NAME, TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=:ttype';
                    $bind = [':ttype' => 'BASE TABLE'];
                    break;
                default:
                    throw new \Exception(sprintf("Unsupported driver %s", $dbSettings['driver']));
            }

            $stmt = Util::getConnection($this->configName)->prepare($sql);
            $stmt->execute($bind);
            if ($stmt->errorCode() > 0) {
                throw new Exception($stmt->errorInfo()[2]);
            }

            $tables = $stmt->fetchAll(PDO::FETCH_NUM);

            $printer = new PsrPrinter();

            $this->output->out('Generating files:');
            foreach ($tables as $tableRow) {
                switch (strtolower($dbSettings['driver'])) {
                    case 'pgsql':
                        $tableName = $tableRow[1];
                        $tableSchema = $tableRow[0];
                        break;
                    case 'mysql':
                        $tableName = $tableRow[0];
                        $tableSchema = '';
                        break;
                    case 'sqlsrv':
                    case 'odbc':
                        $tableName = $tableRow[0];
                        $tableSchema = $tableRow[1];
                        break;
                    default:
                        throw new \Exception('Unsupported driver ' . $dbSettings['driver']);
                }

                $this->output->inline('Table ' . $tableName . '... ');
                $modelsDirRules = Util::getSettingsValue('subfolder_rules', $this->configName);
                $CCName = Transform::CCName($tableName);
                $folder = '';
                $namespacePrefix = '';
                if ($modelsDirRules) {
                    foreach ($modelsDirRules as $rule => $_params) {
                        if (preg_match('/(' . $rule . ')(.+)/', $tableName, $result)) {
                            $folder = $_params['folder'] . DIRECTORY_SEPARATOR;
                            $namespacePrefix = str_replace('/', '\\', $_params['folder']);
                            if (isset($_params['strip']) && $_params['strip']) {
                                $CCName = Transform::CCName($result[2]);
                            }
                            break;
                        }
                    }
                }

                $tableInfo = [
                    'table_name' => $tableName,
                    'dir_name' => Util::getSettingsValue(
                        'path_models',
                        $this->configName
                    ),
                    'file_name' => $folder . DIRECTORY_SEPARATOR . $CCName . '.php',
                    'class_namespace' => Util::getSettingsValue(
                            'models_namespace',
                            $this->configName
                        ) . 'Logic\\' . $namespacePrefix,
                    'base_class_namespace' => Util::getSettingsValue(
                            'models_namespace',
                            $this->configName
                        ) . 'Base\\' . $namespacePrefix,
                    'base_class_extends' => Util::getSettingsValue(
                        'base_class_extends',
                        $this->configName
                    ) ?: ActiveRecordAbstract::class,
                    'class_name' => $CCName,
                    'fields' => [],
                ];

                $LogicModel = new ClassType($tableInfo['class_name']);
                $LogicModel->setExtends(
                        ['Base_' . $tableInfo['class_name'] => $tableInfo['base_class_namespace'] . '\\' . $tableInfo['class_name']]
                    )->setName($tableInfo['class_name'])->setComment(
                        sprintf("LogicModel class for table %s", $tableInfo['table_name'])
                    );

                $LogicNamespace = new PhpNamespace($tableInfo['class_namespace']);
                $LogicNamespace->addUse(
                    $tableInfo['base_class_namespace'] . '\\' . $tableInfo['class_name'],
                    'Base_' . $tableInfo['class_name']
                );
                $LogicNamespace->add($LogicModel);

                $this->writeModel(
                    $tableInfo['dir_name'] . DIRECTORY_SEPARATOR . 'Logic' . DIRECTORY_SEPARATOR . $tableInfo['file_name'],
                    $LogicNamespace
                );

                $this->output->inline('[<green>Logic</green>] ');

                $BaseModel = new ClassType($tableInfo['class_name']);
                $BaseModel->setExtends($tableInfo['base_class_extends'])->setAbstract(true);
                $BaseNamespace = new PhpNamespace($tableInfo['base_class_namespace']);
                $BaseNamespace->addUse($tableInfo['base_class_extends']);
                $BaseNamespace->add($BaseModel);

                $writeConfigName = $this->configName;
                $readConfigName = Util::getSettingsValue('read_connection', $this->configName);

                if (null === $readConfigName) {
                    $readConfigName = $this->configName;
                }

                if ($writeConfigName !== 'default') {
                    $BaseModel->addProperty('SimpleObjectConfigNameWrite', $writeConfigName)->setProtected(
                        )->addComment('Config name for write connection')->setStatic()->setType('string');
                }
                if ($readConfigName !== 'default') {
                    $BaseModel->addProperty('SimpleObjectConfigNameRead', $readConfigName)->setProtected()->addComment(
                            'Config name for read connection'
                        )->setStatic()->setType('string');
                }
                $BaseModel->addProperty('TableName', $tableInfo['table_name'])->setType('string')->setProtected(
                    )->setStatic()->addComment('Model database table name');

                $propertiesMapping = [];
                $Comments = [];
                $dataTransformRules = [];
                $colVal = [];

                $sql = 'SELECT * FROM information_schema.columns WHERE table_name = :table';

                $bind = [
                    ':table' => $tableName,
                    ':database' => $dbSettings['database'],
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
                                'read' => ['date2time' => []],
                                'write' => ['time2date' => ['format' => 'Y-m-d']],
                            ];
                            $colVal[$colName] = 'integer';
                            break;
                        case 'timestamp':
                        case 'timestamp without time zone':
                        case 'datetime':
                            $dataTransformRules[$colName] = [
                                'read' => ['date2time' => []],
                                'write' => ['time2date' => ['format' => 'Y-m-d H:i:s']],
                            ];
                            $colVal[$colName] = 'integer';
                            break;
                        case 'tinyint':
                        case 'bit':
                            $dataTransformRules[$colName] = [
                                'read' => ['digit2boolean' => []],
                                'write' => ['boolean2digit' => []],
                            ];
                            $colVal[$colName] = 'boolean';
                            break;
                        case 'int':
                            $colVal[$colName] = 'integer';
                            break;
                        case 'json':
                        case 'jsonb':
                            $dataTransformRules[$colName] = [
                                'read' => ['unjsonize' => []],
                                'write' => ['jsonize' => []],
                            ];
                            $colVal[$colName] = 'array';
                            break;
                        case 'enum':
                        default:
                            $colVal[$colName] = 'string';
                            break;
                    }
                    if (!empty($_row['COLUMN_COMMENT'])) {
                        $Comments[$colName] = $_row['COLUMN_COMMENT'];
                    }
                }
                $BaseModel->addProperty('propertiesMapping', $propertiesMapping)->setType('array')->setProtected(
                    )->addComment('Model properties for table field mapping')->setStatic();
                $BaseModel->addProperty('dataTransformRules', $dataTransformRules)->setProtected()->setType(
                        'array'
                    )->addComment('Transformations for reading and writing')->setStatic();

                $BaseModel->addComment(
                    'Base class for model ' . $tableInfo['class_namespace'] . '\\' . $tableInfo['class_name']
                );
                foreach ($propertiesMapping as $tableField => $property) {
                    $_ = '@property ' . (array_key_exists(
                            $tableField,
                            $colVal
                        ) ? $colVal[$tableField] : '') . ' $' . $property;

                    if (isset($Comments[$tableField])) {
                        $_ .= sprintf(" %s", $Comments[$tableField]);
                    }

                    $BaseModel->addComment($_);
                }

                $this->writeModel(
                    $tableInfo['dir_name'] . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . $tableInfo['file_name'],
                    $BaseNamespace,
                    true
                );
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
            throw new RuntimeException('path_models is empty');
        }
        $baseModelsDir = $modelsSuperDir . DIRECTORY_SEPARATOR . 'Base';
        $finalModelsDir = $modelsSuperDir . DIRECTORY_SEPARATOR . 'Logic';
        if (!file_exists($baseModelsDir)) {
            if (!mkdir($baseModelsDir, 0755, true) && !is_dir($baseModelsDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $baseModelsDir));
            }
        }
        if (!file_exists($finalModelsDir)) {
            if (!mkdir($finalModelsDir, 0755, true) && !is_dir($finalModelsDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $finalModelsDir));
            }
        }

        $this->output->bold('Removing all base models');

        if (file_exists($modelsSuperDir . DIRECTORY_SEPARATOR . 'Base')) {
            $this->wipeBaseModels($modelsSuperDir . DIRECTORY_SEPARATOR . 'Base');
        }
    }

    protected function wipeBaseModels($dirName): void
    {
        $dir = new \FilesystemIterator($dirName);
        foreach ($dir as $item) {
            if ($item->isDir()) {
                $this->wipeBaseModels($item->getRealPath());
                rmdir($item->getRealPath());
                continue;
            }
            //$this->output->red()->out($item->getRealPath());
            unlink($item->getRealPath());
        }
    }

    /**
     * @param string $filename
     * @param string $contents
     * @param bool $base
     *
     * @return bool|int
     */
    protected function writeModel(string $filename, string $contents, $base = false): bool|int
    {
        $path = $filename;
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        if (!file_exists($path)) {
            return file_put_contents(
                $path,
                ($base ? $this->getBaseModelHeader() : $this->getLogicModelHeader()) . $contents
            );
        }
        return false;
    }

    /**
     * @return string
     */
    protected function getBaseModelHeader(): string
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

    /**
     * @return string
     */
    protected function getLogicModelHeader(): string
    {
        return <<<LOGICMODEL
<?php
/**
 * This file created automatically by SimpleObject model generator
 * This file will NOT be deleted on next models generation.
 */

LOGICMODEL;
    }

}