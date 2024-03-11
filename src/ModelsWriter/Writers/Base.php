<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ModelsWriter\Schemas\TableSchema;

class Base extends AbstractWriter
{
    protected string $modelType='Base';


    public function write()
    {
        $model = new ClassType($this->className);
        $model->setExtends($this->classExtends)
            ->setComment(
                sprintf("BaseModel class for table %s", $this->tableSchema->tableName)
            );
        $namespace = new PhpNamespace($this->getFullNamespace());
        $namespace->addUse(
            $this->classExtends,
        );
        $namespace->add($model);

        echo $namespace.PHP_EOL;
    }

}