<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Nette\PhpGenerator\ClassType;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

abstract class AbstractWriter implements ModelWriterInterface
{
    protected string $modelType;

    public function __construct(
        public readonly ConnectionConfig $connectionConfig,
        public readonly TableSchema $tableSchema,
        public readonly string $className,
        public readonly string $directory,
        public readonly string $subDirectory,
        public readonly string $classNamespace,
        public readonly string $classNamespaceAddon,
        public readonly string $classExtends
    ) {}

    public function getFullNamespace(): string
    {
        return $this->classNamespace.'\\'.$this->modelType.$this->classNamespaceAddon;
    }

    public function getFullDirectoryName(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$this->modelType.(empty($this->subDirectory)?DIRECTORY_SEPARATOR:$this->subDirectory);
    }

    public function dirExists(): bool
    {
        return file_exists($this->getFullDirectoryName());
    }

    public function createDir(): bool
    {
        if(!$this->dirExists()){
            return mkdir(directory: $this->getFullDirectoryName(), permissions: 0755, recursive: true);
        }
        return true;
    }

    public function fileExists(): bool
    {
        return file_exists($this->getFullDirectoryName().DIRECTORY_SEPARATOR.$this->className.'.php');
    }

    protected function writeFile(string $contents): bool
    {
        $this->createDir();
        $path = $this->getFullDirectoryName().$this->className.'.php';
        //echo 'W: '.$this->getFullNamespace().'\\'.$this->className.' => '.$path.PHP_EOL;

        if (!file_exists($path)) {
            return (bool) file_put_contents(
                $path,
                '<?php'.PHP_EOL.$this->getModelHeader().$contents
            );
        }
        return true;
    }

    protected function getModelHeader(): string
    {
        return '';
    }

}