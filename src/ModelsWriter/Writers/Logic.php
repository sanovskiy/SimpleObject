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
            '%s\\%s',
            $this->getFullNamespace('Base'),
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

    protected function getModelHeader(): string
    {
        $curDate = (new \DateTime())->format('Y-m-d H:i:s');
        return <<<LOGICMODEL
/**
 * This file created automatically {$curDate} by SimpleObject model generator
 * This file will NOT be deleted on next models generation.
 */

LOGICMODEL;
    }


}