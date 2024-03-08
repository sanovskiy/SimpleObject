<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;

abstract class DataTransformerAbstract implements DataTransformerInterface
{
    public function __construct(protected string $databaseDriver)
    {
        if (!in_array($this->databaseDriver,['mysql','pgsql','mssql'])){
            throw new InvalidArgumentException('Unsupported database driver');
        }
    }


}