<?php
/**
 * Model generation file example
 */

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Sanovskiy\SimpleObject\Util;

try {
    Util::init([
        'dbcon'            => [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => '',
            'database' => 'database',
            'charset'  => 'utf8'
        ],
        'path_models'      => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models',
        'models_namespace' => 'project\\models\\',
        'read_connection'  => null,
        'write_connection' => null
    ]);

    Util::reverseEngineerModels();
} catch (Exception $e) {
    die('Something went wrong. ' . $e->getMessage());
}
