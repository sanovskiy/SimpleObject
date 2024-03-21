<?php

namespace Sanovskiy\SimpleObject\ModelsWriter;

use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\NoReturn;
use League\CLImate\CLImate;
use RuntimeException;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ConnectionManager;
use Sanovskiy\SimpleObject\ExtendedCLIMate;
use Sanovskiy\SimpleObject\ModelsWriter\Parsers\ParserAbstract;
use Sanovskiy\SimpleObject\ModelsWriter\Parsers\ParserInterface;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\ColumnSchema;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;
use Sanovskiy\SimpleObject\ModelsWriter\Writers\Base;
use Sanovskiy\SimpleObject\ModelsWriter\Writers\Logic;
use Sanovskiy\Utility\NamingStyle;
use Sanovskiy\Utility\Strings;
use Sanovskiy\Utility\VoidObject;

class ModelsGenerator
{
    private ParserInterface $parser;
    private CLImate|VoidObject $term;

    protected static ExtendedCLIMate|VoidObject|null $termInstance = null;

    protected static function getTerm($isSilent = false): VoidObject|ExtendedCLIMate
    {
        if (self::$termInstance === null) {
            if (!$isSilent) {
                self::$termInstance = new ExtendedCLIMate();
                self::$termInstance->setIndentationCharacter('  ');
            } else {
                self::$termInstance = new VoidObject();
            }
        }
        return self::$termInstance;
    }

    public static function reverseEngineerModels(bool $isSilent = false): bool
    {
        $term = self::getTerm($isSilent);
        $tStart = microtime(as_float: true);
        $steps = 0;
        foreach (ConnectionManager::getConnectionNames() as $connectionName) {
            $term->increaseIndent();
            $generator = new self(ConnectionManager::getConfig($connectionName), $isSilent);
            $generator->run();
            $term->decreaseIndent();
            $steps++;
        }
        $tEnd = microtime(as_float: true);
        $term->resetIndent();
        if ($steps > 1) {
            $term->out('');
            $term->out('All done.');
            $term->out('Time taken: ' . number_format((float)($tEnd - $tStart), 4, '.', '') . ' seconds');
            $term->out('');
        }
        return true;
    }

    public function __construct(public readonly ConnectionConfig $connectionConfig, private bool $isSilent = false)
    {
        $this->term = self::getTerm($this->isSilent);
        $this->parser = ParserAbstract::factory($this->connectionConfig);
    }

    public function run(): bool
    {
        $tStart = microtime(as_float: true);
        $this->printStartMessage();
        try {
            $this->prepareDirs();
            $this->generateModels();
            $this->printExecutionTime($tStart);
            return true;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    private function printStartMessage(): void
    {
        $this->term->out("Reverse engineering database " . $this->connectionConfig->getDatabase() . ' (' . $this->connectionConfig->getName() . ')');
        $this->term->increaseIndent();
    }

    private function generateModels(): void
    {
        $this->term->newline();
        $this->term->out('Generating files:');
        $this->term->increaseIndent();
        $tables = $this->parseDatabaseTables();
        $this->generateModelsFromTables($tables);
        $this->term->decreaseIndent();
    }

    private function parseDatabaseTables(): array
    {
        $tables = [];
        foreach ($this->parser->getDatabaseTables() as $tableName => $databaseTable) {
            $tables[$tableName] = $this->parseTable($tableName, $databaseTable);
        }
        return $tables;
    }

    #[ArrayShape(['Base' => "\Sanovskiy\SimpleObject\ModelsWriter\Writers\Base", 'Logic' => "\Sanovskiy\SimpleObject\ModelsWriter\Writers\Logic"])]
    private function parseTable(string $tableName, TableSchema $databaseTable): array
    {
        $modelsDirRules = $this->connectionConfig->getSubFolderRules();
        $ClassName = $databaseTable->getModelName();
        $folder = '';
        $namespaceAddon = '';
        if ($modelsDirRules) {
            foreach ($modelsDirRules as $rule => $_params) {
                if (preg_match('/^(' . $rule . ')(.+)/', $tableName, $result)) {
                    $namespaceAddon = '\\' . trim(
                            str_replace(
                                '/',
                                '\\',
                                $_params['folder'] ?? throw new RuntimeException('Missing folder for rule ' . $rule . ' in database config')
                            ), '\\');
                    $folder = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $namespaceAddon) . DIRECTORY_SEPARATOR;

                    if (isset($_params['strip']) && $_params['strip']) {
                        $ClassName = NamingStyle::toCamelCase($result[2], capitalizeFirstCharacter: true);
                    }
                    break;
                }
            }
        }

        return [
            'Base' => new Base(
                connectionConfig: $this->connectionConfig,
                tableSchema: $databaseTable,
                className: $ClassName,
                directory: $this->connectionConfig->getModelsPath(),
                subDirectory: $folder,
                classNamespace: $this->connectionConfig->getModelsNamespace(),
                classNamespaceAddon: $namespaceAddon,
                classExtends: $this->connectionConfig->getBaseExtends()
            ),
            'Logic' => new Logic(
                connectionConfig: $this->connectionConfig,
                tableSchema: $databaseTable,
                className: $ClassName,
                directory: $this->connectionConfig->getModelsPath(),
                subDirectory: $folder,
                classNamespace: $this->connectionConfig->getModelsNamespace(),
                classNamespaceAddon: $namespaceAddon,
                classExtends: $this->connectionConfig->getBaseExtends()
            )
        ];
    }

    private function generateModelsFromTables(array $tables): void
    {
        $refs = $this->generateReferences($tables);
        $longest = max(array_map(fn($v) => strlen($v), array_keys($tables)));

        foreach ($tables as $tableName => $writers) {
            if (!empty($refs[$tableName])) {
                $tables[$tableName]['Base']->setReferences($refs[$tableName]);
            }
        }
        foreach ($tables as $tableName => $writers) {
            $this->generateModelForTable($tableName, $writers, $refs, $longest);
        }
    }

    private function generateModelForTable(string $tableName, array $writers, array $refs, int $longest): void
    {
        $this->printTableGenerationMessage($tableName, $longest);
        $this->generateBaseModel($writers['Base']);
        $this->term->inline('[ <blue>Base</blue> ] ');
        $this->generateLogicModel($writers['Logic']);
        $this->term->inline('[ <light_cyan>Logic</light_cyan> ] ');
        $this->term->newline();
    }

    private function generateReferences(array $tables): array
    {
        $refs = [];
        foreach ($tables as $tableName => $writers) {
            $columns = $writers['Base']->tableSchema->getColumns();
            foreach ($columns as $colName => $column) {
                if (!empty($column->references['table'])) {
                    $refs = $this->generateToManyReference($refs, $tableName, $column, $writers);
                    $refs = $this->generateToOneReference($refs, $tableName, $colName, $tables[$column->references['table']]['Logic']->className, $column, $writers);
                }
            }
        }
        ksort($refs);
        print_r([
            'acl_person'=>$refs['acl_person'],
            'acl_crowd_person'=>$refs['acl_crowd_person'],
            'acl_crowd'=>$refs['acl_crowd']
        ]);
        return $refs;
    }

    private function generateToOneReference(array $refs, string $tableName, string $colName, $refTable, ColumnSchema $column, array $writers): array
    {
        // Many-to-One relationship
        $refs[$tableName]['one'][$colName] = [
            'localProperty' => $colName,
            'class' => $writers['Logic']->getFullNamespace() . '\\' . NamingStyle::toCamelCase($refTable, true),
            'property' => NamingStyle::toCamelCase($column->references['column'], true)
        ];
        return $refs;
    }

    private function generateToManyReference(array $refs, string $tableName, ColumnSchema $column, array $writers): array
    {
        // One-to-Many relationship
        $refs[$column->references['table']]['many'][$column->references['column']][] = [
            'class' => $writers['Logic']->getFullNamespace() . '\\' . $writers['Logic']->className,
            'property' => NamingStyle::toCamelCase($column->name, true)
        ];
        return $refs;
    }

    private function printTableGenerationMessage(string $tableName, int $longest): void
    {
        $this->term->inline($this->term->getIndentStr() . sprintf("Table %s %s", $tableName, str_repeat(' ', ($longest + 4 - strlen($tableName)))));
    }

    private function generateBaseModel(Base $baseModel): void
    {
        $baseModel->write();
    }

    private function generateLogicModel(Logic $logicModel): void
    {
        $logicModel->write();
    }

    private function printExecutionTime(float $tStart): void
    {
        $tEnd = microtime(as_float: true);
        $this->term->decreaseIndent();
        $this->term->out('');
        $this->term->out('Time taken: ' . number_format((float)($tEnd - $tStart), 4, '.', '') . ' seconds');
        $this->term->out('');
    }

    #[NoReturn] private function handleException(Exception $e): void
    {
        $this->term->resetIndent();
        $this->term->out('');
        $this->term->red()->error($e->getMessage());
        $this->term->error($e->getTraceAsString());
        exit(1);
    }

    private function prepareDirs(): void
    {
        $modelsSuperDir = $this->connectionConfig->getModelsPath();
        if (empty($modelsSuperDir)) {
            throw new InvalidArgumentException('Configuration parameter path_models is empty.');
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

        $this->term->bold('Removing all base models');
        $this->term->increaseIndent();
        if (file_exists($modelsSuperDir . DIRECTORY_SEPARATOR . 'Base')) {
            $this->wipeBaseModels($modelsSuperDir . DIRECTORY_SEPARATOR . 'Base');
        }
        $this->term->decreaseIndent();

    }

    private function wipeBaseModels(string $dirName): void
    {
        $dir = new FilesystemIterator($dirName);
        foreach ($dir as $item) {
            if ($item->isDir()) {
                $this->wipeBaseModels($item->getRealPath());
                $this->term->out('Dir <light_blue>' . Strings::removeCommonPrefix($item->getRealPath(), __DIR__)[0] . '</light_blue> wiped and removed');
                rmdir($item->getRealPath());
                continue;
            }
            $this->term->lightGray()->out('Removed ' . Strings::removeCommonPrefix($item->getRealPath(), __DIR__)[0]);
            unlink($item->getRealPath());
        }
        unset($dir);
    }

    /**
     * @return bool
     */
    public function isSilent(): bool
    {
        return $this->isSilent;
    }

    /**
     * @param bool $isSilent
     */
    public function setIsSilent(bool $isSilent): void
    {
        $this->isSilent = $isSilent;
    }


}