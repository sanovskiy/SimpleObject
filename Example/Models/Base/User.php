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

/**
 * Class Model_Base_User
 */
class Model_Base_User extends SimpleObject_Abstract
{
    public $DBTable = 'user';
    protected $TFields = array(
        0 => 'id',
        1 => 'login',
        2 => 'password',
        3 => 'email',
        4 => 'name',
        5 => 'is_admin',
        6 => 'ban_expiration',
        7 => 'is_activated',
        8 => 'comment',
    );

    protected $Properties = array(
        0 => 'ID',
        1 => 'Login',
        2 => 'Password',
        3 => 'Email',
        4 => 'Name',
        5 => 'IsAdmin',
        6 => 'BanExpiration',
        7 => 'IsActivated',
        8 => 'Comment',
    );

    protected $field2PropertyTransform = array(
        5 => 'digit2boolean',
        6 => 'date2time',
        7 => 'digit2boolean',

    );

    protected $property2FieldTransform = array(
        5 => 'boolean2digit',
        6 => 'time2date',
        7 => 'boolean2digit',

    );

    public $ID;
    public $Login;
    public $Password;
    public $Email;
    public $Name;
    public $IsAdmin;
    public $BanExpiration;
    public $IsActivated;
    public $Comment;
}
