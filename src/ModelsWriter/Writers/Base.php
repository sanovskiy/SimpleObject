<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use DateTime;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Sanovskiy\SimpleObject\Collections\Collection;
use Sanovskiy\Utility\NamingStyle;

class Base extends AbstractWriter
{
    protected string $modelType = 'Base';
    protected array $references = [];

    public function write(): bool
    {
        $model = new ClassType($this->className);
        $namespace = new PhpNamespace($this->getFullNamespace());
        $namespace->addUse(
            $this->classExtends,
        );
        $namespace->add($model);

        $model->setExtends($this->classExtends)
            ->setComment(
                sprintf("BaseModel class for table %s", $this->tableSchema->tableName)
            );
        $model->addProperty('SimpleObjectConfigNameRead',$this->connectionConfig->getName())->setProtected()->setType('string')->setStatic();
        $model->addProperty('SimpleObjectConfigNameWrite',$this->connectionConfig->getName())->setProtected()->setType('string')->setStatic();

        $propertiesMapping = [];
        $addUse = [];
        $transformations = [];

        $maxColumnNameLength = max(array_map('strlen', array_keys($this->tableSchema->getColumns())));
        foreach ($this->tableSchema->getColumns() as $colName => $columnSchema) {
            $_property = NamingStyle::toCamelCase($colName, capitalizeFirstCharacter: true);
            $propertiesMapping[$colName] = $_property;
            $comment = sprintf(
                '@property %-'.$maxColumnNameLength.'s $%-'.$maxColumnNameLength.'s Uses value from %s (%s)',
                $columnSchema->getPHPType(),
                $_property,
                $columnSchema->name,
                $columnSchema->data_type
            );
            $model->addComment($comment);
            if ($columnSchema->getPHPType() === DateTime::class) {
                $addUse[] = '\\' . DateTime::class;
            }
            if (($_trans = $columnSchema->getDefaultTransformation()) && !empty($_trans['transformerClass'])){
                if (class_exists($_trans['transformerClass'])){
                    $namespace->addUse($_trans['transformerClass']);
                    $_trans['transformerClass'] = new Literal(pathinfo($_trans['transformerClass'],PATHINFO_FILENAME).'::class') ;
                }
                $transformations[$colName] = $_trans;
            }
        }

        $model->addProperty('propertiesMapping', $propertiesMapping)->setType('array')
            ->setProtected()->addComment('Model properties for table field mapping')->setStatic();
        $model->addProperty('dataTransformRules', $transformations)->setType('array')->setStatic()
            ->setProtected()->addComment('Default transformations for database values');



        if(!empty($this->references['one'])){
            foreach ($this->references['one'] as $refParent){
                $namespace->addUse($refParent['class']);
                $refObjectName = pathinfo($refParent['class'],PATHINFO_FILENAME);
                $_ = $model->addMethod('get'.$refObjectName)
                    ->setPublic()
                    ->setReturnType($refParent['class'])
                    ->setReturnNullable(true);
                $_->setBody(sprintf("return ".$refObjectName."::one(['%s'=>\$this->%s]);", NamingStyle::toSnakeCase($refParent['property']), NamingStyle::toCamelCase($refParent['localProperty'],true)));
            }
        }
        if(!empty($this->references['many'])){
            $namespace->addUse(Collection::class);
            foreach ($this->references['many'] as$localProperty=>$refChild){
                $namespace->addUse($refChild['class']);
                $refObjectName = pathinfo($refChild['class'],PATHINFO_FILENAME);
                $_ = $model->addMethod('get'.$refObjectName.'s')
                    ->setPublic()
                    ->setReturnType(Collection::class);
                $_->setBody(sprintf("return ".$refObjectName."::find(['%s'=>\$this->%s]);", NamingStyle::toSnakeCase($refChild['property']), NamingStyle::toCamelCase($localProperty,true)));
            }
        }



        if (!empty($addUse)) {
            foreach ($addUse as $_className) {
                $namespace->addUse($_className);
            }

        }
        $model->addProperty('TableName', $this->tableSchema->tableName)->setStatic()->setProtected()->setType('string');
        return $this->writeFile((string)$namespace);
    }

    public function setReferences(array $references)
    {
        $this->references = $references;
    }

    protected function getModelHeader(): string
    {
        $curDate = (new \DateTime())->format('Y-m-d H:i:s');
        return <<<BASEMODEL
/**
 * This file created automatically {$curDate} by SimpleObject model generator
 * DO NOT modify this file because it WILL BE DELETED next time you generate models.
 * Use logic model instead
 */

BASEMODEL;
    }


}