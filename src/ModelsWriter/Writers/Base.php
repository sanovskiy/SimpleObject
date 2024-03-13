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

        $propertiesMapping = [];
        $addUse = [];
        $transformations = [];
        foreach ($this->tableSchema->getColumns() as $colName => $columnSchema) {
            $_property = NamingStyle::toCamelCase($colName, capitalizeFirstCharacter: true);
            $propertiesMapping[$colName] = $_property;
            $model->addComment('@property ' . $columnSchema->getPHPType() . ' ' . $_property.' '.$columnSchema->data_type);
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
        $model->addProperty('transformations', $transformations)->setType('array')
            ->setProtected()->addComment('Default transformations for database values');



        if(!empty($this->references['one'])){
            foreach ($this->references['one'] as $refParent){
                $namespace->addUse($refParent['class']);
                $refObjectName = pathinfo($refParent['class'],PATHINFO_FILENAME);
                $_ = $model->addMethod('get'.$refObjectName)
                    ->setPublic()
                    ->setReturnType($refParent['class'])
                    ->setReturnNullable(true);
                $_->setBody(sprintf("return ".$refObjectName."::one(['%s'=>\$this->%s]);", $refParent['property'], NamingStyle::toCamelCase($refParent['localProperty'],true)));
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
                $_->setBody(sprintf("return ".$refObjectName."::find(['%s'=>\$this->%s]);", $refChild['property'], NamingStyle::toCamelCase($localProperty,true)));
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


}