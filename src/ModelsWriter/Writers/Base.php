<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Writers;

use DateTime;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpNamespace;
use Sanovskiy\SimpleObject\Collections\Collection;
use Sanovskiy\SimpleObject\Collections\QueryResult;
use Sanovskiy\SimpleObject\TransformRule;
use Sanovskiy\Utility\NamingStyle;

class Base extends AbstractWriter
{
    protected string $modelType = 'Base';
    protected array $references = [];

    public function write(): bool
    {
        // Create a new instance of ClassType and PhpNamespace for the model
        $model = new ClassType($this->className);
        $namespace = new PhpNamespace($this->getFullNamespace());
        $namespace->add($model);
        // Add "use" statement for the class extended by the model
        $namespace->addUse($this->classExtends);

        // Set up model class and add properties related to configuration
        $model->setExtends($this->classExtends)->setComment(
            sprintf("BaseModel class for table %s", $this->tableSchema->tableName)
        );
        $model->addProperty('SimpleObjectConfigNameRead', $this->connectionConfig->getName())
            ->setProtected()->setType('string')->setStatic();
        $model->addProperty('SimpleObjectConfigNameWrite', $this->connectionConfig->getName())
            ->setProtected()->setType('string')->setStatic();

        // Initialize arrays to store properties mapping, additional "use" statements, and transformations
        $propertiesMapping = [];
        $initMethod = $model->addMethod('initStatic')
            ->setFinal(true)
            ->setPublic()
            ->setStatic()
            ->setReturnType('void');
        $namespace->addUse(TransformRule::class);
        $model->addProperty('dataTransformRules', [])->setType('array')->setStatic()
            ->setProtected()->addComment('Default transformations for database values')->addComment('@var TransformRule[]');
        $model->addProperty('initialized')->setStatic()->setPrivate()->setValue(false)->setType('bool');
        $initMethodBody = ['if (self::$initialized) {return;}'];

        $maxColumnNameLength = max(array_map('strlen', array_keys($this->tableSchema->getColumns())));
        foreach ($this->tableSchema->getColumns() as $colName => $columnSchema) {
            $_property = $propertiesMapping[$colName] = NamingStyle::toCamelCase($colName,true);
            $comment = sprintf(
                '@property %-' . $maxColumnNameLength . 's $%-' . $maxColumnNameLength . 's Uses value from %s (%s)',
                $columnSchema->getPHPType(),
                $_property,
                $columnSchema->name,
                $columnSchema->data_type
            );
            $model->addComment($comment);
            $transform = $columnSchema->getDefaultTransformation();
            if (!empty($transform['transformerClass'])) {
                $transformerClass = $transform['transformerClass'];
                $transformerParams = $transform['transformerParams'] ?? [];
                $propertyType = $columnSchema->getPHPType();
                $namespace->addUse($transformerClass);
                $transformerParamsString = var_export($transformerParams, true);
                $transformerParamsString = str_replace(['array (', ')', "\n", '  '], ['[', ']', '',' '], $transformerParamsString);
                $initMethodBody[] = sprintf('static::$dataTransformRules[\'%s\'] = new TransformRule(%s::class, %s, \'%s\');', $colName, self::extractClassName($transformerClass), $transformerParamsString, $propertyType);
            }
        }
        $initMethodBody[] = 'self::$initialized = true;';

        // Add method body
        $initMethod->setBody(implode("\n",$initMethodBody));

        // Add properties for properties mapping and data transformations
        $model->addProperty('propertiesMapping', $propertiesMapping)->setType('array')
            ->setProtected()->addComment('Model properties for table field mapping')->setStatic();

        // Generate methods for fetching related objects (if any)
        if (!empty($this->references['one'])) {
            foreach ($this->references['one'] as $refParent) {
                $namespace->addUse($refParent['class']);
                $refObjectName = static::extractClassName($refParent['class']);
                $localPropName = NamingStyle::toCamelCase($refParent['localProperty'],true);
                $_method = $model->addMethod(NamingStyle::toCamelCase('get_' . $refParent['localProperty']))
                    ->setPublic()
                    ->setReturnType($refParent['class'])
                    ->setReturnNullable(true);
                $methodBody = [
                    sprintf("if (!is_integer(\$this->%s) || \$this->%s<1){return null;}", $localPropName, $localPropName),
                    sprintf('return %s::one([\'%s\'=>$this->%s]);', $refObjectName, NamingStyle::toSnakeCase($refParent['property']), $localPropName)
                ];
                // Prepare method body based on the provided template
                $_method->setBody(implode(PHP_EOL, $methodBody));
            }
        }
        if (!empty($this->references['many'])) {
            $namespace->addUse(QueryResult::class);
            foreach ($this->references['many'] as $localProperty => $refChildren) {
                foreach ($refChildren as $refChild) {
                    $namespace->addUse($refChild['class']);
                    $refObjectName = static::extractClassName($refChild['class']);
                    $_method = $model->addMethod('get' . $refObjectName . 's')
                        ->setPublic()
                        ->setReturnType(QueryResult::class);
                    $_method->addParameter('filters')->setType('array')->setNullable()->setDefaultValue([])->hasDefaultValue();
                    // Prepare method body based on the provided template
                    $_method->setBody(sprintf('return %s::find(array_merge($filters,[\'%s\'=>$this->%s]));', $refObjectName, NamingStyle::toSnakeCase($refChild['property']), NamingStyle::toCamelCase($localProperty, true)));
                }
            }
        }

        // Add a property for the table name and write the file
        $model->addProperty('TableName', $this->tableSchema->tableName)->setStatic()->setProtected()->setType('string');
        $model->addProperty('TablePK', $this->tablePK)->setStatic()->setProtected()->setType('?string');
        return $this->writeFile((string)$namespace);
    }

    protected static function extractClassName(string $fullClassName): string
    {
        return pathinfo(str_replace('\\', DIRECTORY_SEPARATOR, $fullClassName), PATHINFO_FILENAME);
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

    protected function getModelFooter(): string
    {
        return PHP_EOL.$this->className.'::initStatic();'.PHP_EOL;
    }


}