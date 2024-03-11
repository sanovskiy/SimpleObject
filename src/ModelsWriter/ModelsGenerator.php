<?php

namespace Sanovskiy\SimpleObject\ModelsWriter;

use League\CLImate\CLImate;
use Nette\PhpGenerator\PsrPrinter;
use RuntimeException;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ConnectionManager;
use Sanovskiy\SimpleObject\ModelsWriter\Parsers\ParserAbstract;
use Sanovskiy\SimpleObject\ModelsWriter\Parsers\ParserInterface;
use Sanovskiy\SimpleObject\ModelsWriter\Writers\Base;
use Sanovskiy\Utility\NamingStyle;
use Sanovskiy\Utility\VoidObject;

class ModelsGenerator
{

    private ParserInterface $parser;
    private CLImate|VoidObject $output;

    public static function reverseEngineerModels(bool $silent = false): bool
    {
        foreach (ConnectionManager::getConnectionNames() as $connectionName) {
            $generator = new self(ConnectionManager::getConfig($connectionName), $silent);
            $generator->run();
        }

        return true;
    }

    public function __construct(public readonly ConnectionConfig $connectionConfig, private bool $isSilent = false)
    {
        if (!$this->isSilent) {
            $this->output = new CLImate();
        } else {
            $this->output = new VoidObject();
        }
        $this->parser = ParserAbstract::factory($this->connectionConfig);
    }

    public function run(): bool
    {
        $this->output->out("Reverse engineering database " . $this->connectionConfig->getDatabase() . ' (' . $this->connectionConfig->getName() . ')');
        $this->prepareDirs();

        $this->output->out('Generating files:');
        foreach ($this->parser->getDatabaseTables() as $tableName => $databaseTable) {
            $this->output->inline('Table ' . $tableName . '... ');
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
            $baseWriter = new Base(
                connectionConfig: $this->connectionConfig,
                tableSchema: $databaseTable,
                className: $ClassName,
                directory: $this->connectionConfig->getModelsPath().$folder,
                classNamespace: $this->connectionConfig->getModelsNamespace(),
                classNamespaceAddon: $namespaceAddon,
                classExtends: $this->connectionConfig->getBaseExtends()
            );
            $baseWriter->write();
        }


        return true;
    }

    private function prepareDirs(): void
    {
        $modelsSuperDir = $this->connectionConfig->getModelsPath();
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

    private function wipeBaseModels(string $dirName): void
    {
        $dir = new \FilesystemIterator($dirName);
        foreach ($dir as $item) {
            if ($item->isDir()) {
                $this->wipeBaseModels($item->getRealPath());
                rmdir($item->getRealPath());
                continue;
            }
            $this->output->red()->out($item->getRealPath());
            unlink($item->getRealPath());
        }
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