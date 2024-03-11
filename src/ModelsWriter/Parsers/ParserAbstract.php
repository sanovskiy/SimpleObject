<?php

namespace Sanovskiy\SimpleObject\ModelsWriter\Parsers;

use PDO;
use RuntimeException;
use Sanovskiy\SimpleObject\ConnectionConfig;
use Sanovskiy\SimpleObject\ConnectionManager;

abstract class ParserAbstract implements ParserInterface
{

    protected static array $drivers = [
        'mysql' => ParserMySQL::class,
        'pgsql' => ParserPostgreSQL::class,
        'mssql' => ParserMSSQL::class,
    ];

    public static function factory(ConnectionConfig $config): ParserInterface
    {
        $driver = $config->getDriver();
        $connection = ConnectionManager::getConnection($config->getName());
        $database = $config->getDatabase();

        if (isset(self::$drivers[$driver])) {
            return new self::$drivers[$driver]($connection, $database);
        } else {
            throw new RuntimeException('Unknown driver');
        }
    }

    public function __construct(public readonly PDO $connection, public readonly string $database)
    {
    }

}