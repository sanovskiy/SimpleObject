<?php

namespace Sanovskiy\SimpleObject;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;

class ConnectionManager
{
    /**
     * ['connectionName'=> ConnectionConfig]
     * @var array ConnectionConfig[]
     */
    private static array $connectionConfigs = [];

    /**
     * @var array PDO[]
     */
    private static array $connections = [];

    /**
     * Add a connection configuration.
     *
     * @param ConnectionConfig $connection The connection configuration to add.
     * @return bool True if the connection configuration was added successfully, false otherwise.
     */
    public static function addConnection(ConnectionConfig $connection): bool
    {
        $name = $connection->getName();
        if (isset(self::$connectionConfigs[$name])) {
            return false;
        }

        self::$connectionConfigs[$name] = $connection;
        return true;
    }

    /**
     * Reconnect to the database for the specified configuration.
     *
     * @param string $configName The name of the connection configuration to reconnect.
     * @return bool True if reconnection was successful, false otherwise.
     * @throws Exception If the connection configuration is not found or PDO connection fails.
     */
    public static function reconnect(string $configName): bool
    {
        if (!isset(self::$connectionConfigs[$configName])) {
            throw new Exception("Connection configuration '$configName' not found.");
        }

        /** @var ConnectionConfig $config */
        $config = self::$connectionConfigs[$configName];
        $dsn = $config->getDSN();
        $user = $config->getUser();
        $password = $config->getPassword();

        try {
            self::$connections[$configName] = new PDO($dsn, $user, $password,$config->getConnectionOptions());
            self::$connections[$configName]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$connections[$configName]->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

            switch ($config->getDriver()) {
                case 'mysql':
                    self::$connections[$configName]->exec("SET NAMES {$config->getCharset()}");
                    break;
                case 'pgsql':
                    self::$connections[$configName]->exec("SET CLIENT_ENCODING TO '{$config->getCharset()}'");
                    break;
                case 'mssql':
                    //self::$connections[$configName]->exec("SET NAMES {$config->getCharset()}");
                    // Для SQL Server нет необходимости в установке кодировки
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported database driver: ' . $config->getDriver());
            }

            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to reconnect to database for configuration '$configName': " . $e->getMessage());
        }    }

    /**
     * Get a PDO connection based on the configuration name.
     *
     * @param string $configName The name of the connection configuration.
     * @return PDO The PDO connection.
     * @throws Exception If the connection configuration is not found or PDO connection fails.
     */
    public static function getConnection(string $configName): PDO
    {
        if (!isset(self::$connectionConfigs[$configName])) {
            throw new Exception("Connection configuration '$configName' not found.");
        }

        if (!self::isConnectionAlive($configName)) {
            self::reconnect($configName); // Reconnect if not already connected
        }

        return self::$connections[$configName];
    }


    /**
     * Check if the connection for the specified configuration is alive by executing a simple query.
     *
     * @param string $configName The name of the connection configuration.
     * @return bool True if the connection is alive, false otherwise.
     */
    public static function isConnectionAlive(string $configName): bool
    {
        if (!isset(self::$connections[$configName])) {
            return false;
        }
        try {
            return (bool) self::$connections[$configName]->query('SELECT 1');
        } catch (\Exception $e) {
            return false;
        }
    }
}

