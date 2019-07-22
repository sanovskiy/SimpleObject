<?php

return [
    'database' => [
        'mysql' => [
            'dbcon'            => [
                'adapter'   => 'mysql',
                'host'      => 'localhost',
                'port'      => '3306',
                'user'      => 'phpunit',
                'pass'      => 'phpunit',
                'name'      => 'phpunit_simpleobject',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
            ],
            'path_models'      => implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'models']),
            'models_namespace' => 'Sanovskiy\\SimpleObject\\models\\',
        ],
        'pgsql' => [
            'dbcon'            => [
                'adapter' => 'pgsql',
                'host'    => 'localhost',
                'port'    => '5432',
                'user'    => 'phpunit',
                'pass'    => 'phpunit',
                'name'    => 'phpunit_simpleobject',
                'charset' => 'utf8',
            ],
            'path_models'      => implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'models']),
            'models_namespace' => 'Sanovskiy\\SimpleObject\\models\\',
        ],
    ]
];