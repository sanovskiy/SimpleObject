<?php
$basicConfig = include __DIR__ . DIRECTORY_SEPARATOR . 'phpunit.php';
return [
    'paths'        => [
        'migrations' => implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'tests', 'migrations']),
        'seeds'      => implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 2), 'tests', 'seeds']),
    ],
    'environments' => [
        'mysql' => $basicConfig['database']['mysql']['dbcon'],
        'pgsql' => $basicConfig['database']['pgsql']['dbcon'],
    ]
];
