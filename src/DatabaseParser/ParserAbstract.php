<?php

namespace Sanovskiy\SimpleObject\DatabaseParser;

use PDO;

abstract class ParserAbstract implements ParserInterface
{

    public function __construct(public readonly PDO $connection, public readonly string $database)
    {
    }

}