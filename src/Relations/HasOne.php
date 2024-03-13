<?php

namespace Sanovskiy\SimpleObject\Relations;

use Sanovskiy\SimpleObject\ActiveRecordAbstract;

class HasOne
{
    protected string $relatedModelClass;
    protected string $localKey;
    protected string $foreignKey;
    protected mixed $parentId;

    public function __construct(string $relatedModelClass, string $foreignKey, string $localKey, mixed $parentId)
    {
        $this->relatedModelClass = $relatedModelClass;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->parentId = $parentId;
    }

    public function get(): ?ActiveRecordAbstract
    {
        $relatedModelClass = $this->relatedModelClass;
        /** @var ActiveRecordAbstract $relatedModelClass */
        return $relatedModelClass::one([$this->foreignKey => $this->parentId]);
    }
}