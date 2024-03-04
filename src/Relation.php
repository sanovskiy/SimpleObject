<?php

namespace Sanovskiy\SimpleObject;

class Relation
{
    public function __construct(protected string $localKey, protected string $foreignKey, protected string $relatedModel)
    {
        if (!class_exists($this->relatedModel)) {
            throw new \RuntimeException('Relation model ' . $this->relatedModel . ' not exists');
        }
    }

    public function getRelatedModel(): string
    {
        return $this->relatedModel;
    }

    public function getRelatedModelName(): string
    {
        return basename(str_replace('\\', '/', $this->relatedModel));
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}