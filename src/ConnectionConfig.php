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
 * @method string getConnectionOptions() Returns the connection name.
 */
class ConnectionConfig
{
    public static function factory(array $config, string $name = 'default'): self
    {
        if (!self::validateConfig($config)) {
            throw new InvalidArgumentException("Invalid configuration provided. ".self::$validatorMessage);
        }

        return new self($name, $config['connection'], $config['path_models'], $config['models_namespace']);
    }

    public function __construct(private readonly string $name, private readonly array $connectionInfo, private readonly string $modelsPath, private readonly string $modelsNamespace)
    {
    }

    private static string $validatorMessage = '';
    private static function validateConfig(array $config): bool
    {
        self::$validatorMessage = '';
        $requiredKeys = ['connection', 'path_models', 'models_namespace'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                self::$validatorMessage = 'Missing '.$key.' parameter';
                return false;
            }
        }

        $connectionKeys = ['driver', 'host', 'database', 'user', 'password', 'charset'];
        if (count(array_intersect_key(array_flip($connectionKeys), $config['connection'])) !== count($connectionKeys)) {
            self::$validatorMessage = 'connection section must contain '.implode(', ',$connectionKeys);
            return false;
        }


        $allowedDrivers = ['pgsql', 'mysql', 'mssql'];
        if (!in_array(strtolower($config['connection']['driver']), $allowedDrivers)) {
            self::$validatorMessage = 'Allowed drivers are '.implode(', ',$allowedDrivers);;
            return false;
        }

        return true;
    }

    public function getDSN(): string
    {
        $port = $this->getPort() ? ";port={$this->getPort()}" : '';

        return match ($this->getDriver()) {
            'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $this->getHost(), $this->getPort(), $this->getDatabase(), $this->getCharset()),
            'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s', $this->getHost(), $this->getPort(), $this->getDatabase(), $this->getUser(), $this->getPassword()),
            'mssql' => sprintf('sqlsrv:Server=%s,%s;Database=%s;TrustServerCertificate=true', $this->getHost(), $this->getPort(), $this->getDatabase()),
            default => throw new \InvalidArgumentException('Unsupported database driver: ' . $this->getDriver()),
        };
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
            'getConnectionOptions' => $this->connectionInfo['options']??[],
            default => null,
        };
    }

}

