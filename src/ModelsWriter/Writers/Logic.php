<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class Logic extends AbstractWriter
{
    protected string $modelType = 'Logic';

    public function write()
    {
        $baseClassName = sprintf(
            '%s\\Base%s\\%s',
            $this->classNamespace,
            $this->classNamespaceAddon,
            $this->className
        );

        $model = new ClassType($this->className);
        $namespace = new PhpNamespace($this->getFullNamespace());
        $namespace->addUse($baseClassName, 'Base_' . $this->className);
        $namespace->add($model);
        $model->setExtends($baseClassName)
            ->setComment(
                sprintf("Logic Model class for table %s", $this->tableSchema->tableName)
            );

        $this->writeFile((string)$namespace);

    }

    public function setReferences(array $references)
    {
        return;
    }
}