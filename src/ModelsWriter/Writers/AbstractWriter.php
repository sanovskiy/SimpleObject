<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Nette\PhpGenerator\ClassType;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

abstract class AbstractWriter implements ModelWriterlInterface
{
    protected string $modelType;

    public function __construct(
        public readonly ConnectionConfig $connectionConfig,
        public readonly TableSchema $tableSchema,
        public readonly string $className,
        public readonly string $directory,
        public readonly string $classNamespace,
        public readonly string $classNamespaceAddon,
        public readonly string $classExtends
    ) {}

    public function getFullNamespace(): string
    {
        return $this->classNamespace.'\\'.$this->modelType.$this->classNamespaceAddon;
    }

    public function dirExists(): bool
    {
        return file_exists($this->directory);
    }

    public function createDir(): bool
    {
        if(!$this->dirExists()){
            return mkdir(directory: $this->directory, permissions: 0755, recursive: true);
        }
        return true;
    }

    public function fileExists(): bool
    {
        return file_exists($this->directory.DIRECTORY_SEPARATOR.$this->className.'.php');
    }

    protected function writeFile(string $contents)
    {
        $this->createDir();
        $path = $this->directory.DIRECTORY_SEPARATOR.$this->className.'.php';

        if (!file_exists($path)) {
            return file_put_contents(
                $path,
                $this->getModelHeader().$contents
            );
        }
        return true;
    }

    protected function getModelHeader(): string
    {
        return '';
    }

}