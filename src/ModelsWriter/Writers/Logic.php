<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class Logic extends AbstractWriter
{
    protected string  $modelType='Logic';

    public function write()
    {
        $model = new ClassType($this->className);
        $model->setExtends($this->classExtends)
            ->setComment(
                sprintf("Logic Model class for table %s", $this->tableSchema->tableName)
            );

        $namespace = new PhpNamespace($this->getFullNamespace());
        $namespace->addUse(
            $this->classNamespace.'\\Base'.$this->classNamespaceAddon,
            'Base_'.$this->className
        );
        $namespace->add($model);

        echo $namespace.PHP_EOL;

    }

}