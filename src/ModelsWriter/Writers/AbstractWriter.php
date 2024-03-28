<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\Interfaces\ModelWriterInterface;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

abstract class AbstractWriter implements ModelWriterInterface
{
    protected string $modelType;

    public function __construct(
        public readonly ConnectionConfig $connectionConfig,
        public readonly TableSchema      $tableSchema,
        public readonly string           $className,
        public readonly string           $directory,
        public readonly string           $subDirectory,
        public readonly string           $classNamespace,
        public readonly string           $classNamespaceAddon,
        public readonly string           $classExtends,
        public readonly ?string          $tablePK = null,

    )
    {
    }

    public function getFullNamespace(?string $forceType = null): string
    {
        $ns = $this->classNamespace . '\\' . ($forceType ?? $this->modelType) . $this->classNamespaceAddon;
        while (str_contains($ns, '\\\\')) {
            $ns = str_replace('\\\\', '\\', $ns);
        }
        return $ns;
    }

    public function getFullDirectoryName(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->modelType . (empty($this->subDirectory) ? DIRECTORY_SEPARATOR : $this->subDirectory);
    }

    public function dirExists(): bool
    {
        return file_exists($this->getFullDirectoryName());
    }

    public function createDir(): bool
    {
        if (!$this->dirExists()) {
            return mkdir(directory: $this->getFullDirectoryName(), permissions: 0755, recursive: true);
        }
        return true;
    }

    public function fileExists(): bool
    {
        return file_exists($this->getFullDirectoryName() . DIRECTORY_SEPARATOR . $this->className . '.php');
    }

    protected function writeFile(string $contents): bool
    {
        $this->createDir();
        $path = $this->getFullDirectoryName() . $this->className . '.php';

        if (!file_exists($path)) {
            return (bool)file_put_contents(
                $path,
                '<?php' . PHP_EOL . $this->getModelHeader() . $contents . $this->getModelFooter()
            );
        }
        return true;
    }

    protected function getModelHeader(): string
    {
        return '';
    }

    protected function getModelFooter(): string
    {
        return '';
    }


}