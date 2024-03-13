<?php

namespace Sanovskiy\SimpleObject\Relations;

use Sanovskiy\SimpleObject\ActiveRecordAbstract;
use Sanovskiy\SimpleObject\Collections\Collection;

class HasMany
{
    protected string $relatedModelClass;
    protected string $foreignKey;
    protected string $localKey;
    protected int $parentId;

    public function __construct(string $relatedModelClass, string $foreignKey, string $localKey, int $parentId)
    {
        $this->relatedModelClass = $relatedModelClass;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->parentId = $parentId;
    }

    public function get(): Collection
    {
        /** @var ActiveRecordAbstract $relatedModelClass */
        $relatedModelClass = $this->relatedModelClass;
        return  $relatedModelClass::find([$this->foreignKey => $this->parentId]);
    }
}
