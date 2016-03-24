<?php

/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

$settings = [
    'path_models' => __DIR__ . DIRECTORY_SEPARATOR . 'Models',
    'dbcon' => [
        'host' => 'localhost',
        'user' => 'simpleobject',
        'password' => 'kog46zkVcnYBtFu0',
        'database' => 'simpleobject'
    ]
];

require_once __DIR__ . '/../SimpleObject.php';
SimpleObject::init($settings);

for($i=0;$i<10000;$i++){
    $a = new Model_User(mt_rand(1,100000));
    //$a->test();

    //print_r($a->__toArray());
    //print_r($a->__toArray(true));

    $a->Comment = mt_rand(100000,999999);
    $a->save();
}

//$a = new Model_User(1);
//print_r($a->__toArray());
/*
for($i=0;$i<100000;$i++) {
    $login = 'user' . mt_rand(100000, 999999);
    $b = new Model_User();
    $b->Login = $login;
    $b->Password = 'newpass';
    $b->Email = $login.'@example.org';
    $b->Name = 'New User '.$login;
    $b->save();
    if ($i%10==0){
        if ($i%100==0){
            echo '!';
        } else {
            echo '.';
        }

    }
    //$c=new Model_User($b->ID);
}
echo PHP_EOL;
*/
print_r(SimpleObject::getConnection()->getUsageInfo());