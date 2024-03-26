<?php

namespace Sanovskiy\SimpleObject\DataTransformers;

use InvalidArgumentException;
use Sanovskiy\SimpleObject\Interfaces\DataTransformerInterface;

abstract class DataTransformerAbstract implements DataTransformerInterface
{
    protected static ?string $databaseDriver=null;

    public static function setDatabaseDriver(string $driver)
    {
        if (!in_array($driver,['mysql','pgsql','mssql'])){
            throw new InvalidArgumentException('Unsupported database driver');
        }
        static::$databaseDriver = $driver;
    }


}