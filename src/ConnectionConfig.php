<?php

namespace Sanovskiy\SimpleObject;

use InvalidArgumentException;

/**
 * @method string getDriver() Returns the database driver.
 * @method string getHost() Returns the database host.
 * @method string getDatabase() Returns the database name.
 * @method string getUser() Returns the database user.
 * @method string getPassword() Returns the database password.
 * @method string getCharset() Returns the database charset.
 * @method string|null getPort() Returns the database port or null if not set.
 * @method string getModelsPath() Returns the models path.
 * @method string getModelsNamespace() Returns the models namespace.
 * @method string getName() Returns the connection name.
 */
class ConnectionConfig
{
    public static function factory(array $config, string $name = 'default'): self
    {
        if (!self::validateConfig($config)) {
            throw new InvalidArgumentException("Invalid configuration provided.");
        }

        return new self($name, $config['connection'], $config['path_models'], $config['models_namespace']);
    }

    public function __construct(private readonly string $name, private readonly array $connectionInfo, private readonly string $modelsPath, private readonly string $modelsNamespace)
    {
    }


    private static function validateConfig(array $config): bool
    {
        $requiredKeys = ['connection', 'path_models', 'models_namespace'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                return false;
            }
        }

        $connectionKeys = ['driver', 'host', 'database', 'user', 'password', 'charset'];
        if (count(array_intersect_key(array_flip($connectionKeys), $config['connection'])) !== count($connectionKeys)) {
            return false;
        }


        $allowedDrivers = ['pgsql', 'mysql', 'mssql'];
        if (!in_array(strtoupper($config['connection']['driver']), $allowedDrivers)) {
            return false;
        }

        return true;
    }

    public function getDSN(): string
    {
        $port = $this->getPort() ? ";port={$this->getPort()}" : '';
        return sprintf('%s:host=%s;dbname=%s;charset=%s%s', $this->getDriver(), $this->getHost(), $this->getDatabase(), $this->getCharset(), $port);
    }


    public static function getDefaultPort(string $driver): ?string
    {
        return match (strtoupper($driver)) {
            'PGSQL' => '5432',
            'MYSQL' => '3306',
            'MSSQL' => '1433',
            default => null,
        };
    }

    /** Getters for $this->connectionInfo fields **/


    public function __call($name,$args)
    {
        return match ($name) {
            'getDriver' => strtolower($this->connectionInfo['driver']),
            'getHost' => $this->connectionInfo['host'],
            'getDatabase' => $this->connectionInfo['database'],
            'getUser' => $this->connectionInfo['user'],
            'getPassword' => $this->connectionInfo['password'],
            'getCharset' => $this->connectionInfo['charset'] ?? 'utf8',
            'getPort' => $this->connectionInfo['port'] ?? self::getDefaultPort($this->getDriver()),
            'getModelsPath' => $this->modelsPath,
            'getModelsNamespace' => $this->modelsNamespace,
            'getName' => $this->name,
            default => null,
        };
    }

}

