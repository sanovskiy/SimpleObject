<?php
require_once __DIR__.'/../SimpleObject.php';
SimpleObject::init([
    'models_path' => __DIR__.DIRECTORY_SEPARATOR.'Models'
]);

$a = new Model_User();
$a->test();
