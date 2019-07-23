.. index::
   single: configuration

Configuration
=============

At first you must initialize database connection.
You can make this by calling Sanovskiy\SimpleObject\Util::init($options, $configName)
method for each connection you use

Simple exanple:

.. code-block:: php

    require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

    use Sanovskiy\SimpleObject\Util;

    try {
        Util::init([
            'dbcon' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'user' => 'root',
                'password' => '',
                'database' => 'test',
                'charset' => 'utf8'
            ],
            'path_models' => '/full/path/to/models/directory',
            'models_namespace' => 'project\\models\\'
        ], 'default');

    } catch (\Throwable $e) {
        die('Something went wrong. '.$e->getMessage());
    }

After this you can access PDO connection directly through Util::getConnection('default')

.. note::

    You can omit 'default' connection name because SimpleObject uses 'default' as the default connection name.


If you planning to use separate connections for read and write (master-slave database setup) нщг can specify different
connections for reading and writing

.. code-block:: php

    $config = [
        'default'      => [
            'dbcon'            => [
                'driver'   => 'mysql',
                'host'     => 'localhost',
                'user'     => 'root',
                'password' => '',
                'database' => 'test',
                'charset'  => 'utf8'
            ],
            'path_models'      => '/full/path/to/models/directory',
            'models_namespace' => 'project\\models\\',
            'read_connection'  => 'default_read'
        ],
        'default_read' => [
            'dbcon'            => [
                'driver'   => 'mysql',
                'host'     => 'localhost',
                'user'     => 'root',
                'password' => '',
                'database' => 'test',
                'charset'  => 'utf8'
            ],
            'path_models'      => '/full/path/to/models/directory',
            'models_namespace' => 'project\\models\\',
            'write_connection' => 'default'
        ],
    ];
    foreach ($config as $name => $c) {
        Util::init($c, $name);
    }

Generating models
=================

Just call Util::reverseEngineerModels(bool $silent) to generate models for all configs initialized.
If you supply TRUE as parameter, generator will not make any info output except errors.