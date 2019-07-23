# SimpleObject

More detailed documentation - https://simpleobject.readthedocs.io 

## Installation
```bash
composer require sanovskiy/simple-object
```

## How to generate models
See cli/generate-models-example.php

## About PKs
Don't forget that table PK must be named `id`

## Model properties naming


All models and model properties properties are named in CamelCase by splitting table name or column by underscores.
I.e: field model_id become property ModelId. Column SomeLongFieldName become Somelongfieldname.

Use robmorgan/phinx (https://github.com/cakephp/phinx) to make fully supported tables

## Using models

Finding entities
```php
$users = User::find(['role' => 'admin','is_active > ?' => 0]);
```

Getting entity
```php
$user = User::one(['email' => 'user@example.org']);
```
OR by PK
```php
$user = new User(1);
```

## Populating models

You can use Collection to form self-controlled collection

```php
$sql = 'Complex query which selects  all columns from user table';
$bind = ['some'=>'bind','params'=>'for select'];

$users = User::factory($sql,$bind);
```

## Accessing properties

Model propertias are accessible by column name and CamelCase variant
```php
$user = new User();
$user->UserName = 'johnsmith'; // table column is user_name
$user->email = 'johnsmith@example.org'; // also works as Email
$user->is_active = false;
$user->save();
```

## Data transforming
All data can be transformed after loading and before writing.
Rules are stores in `static::$dataTransformRules`

You can specify you own transformations for read and write.
```php
class User extends Base_User
{

    const ROLE_ADMIN = 'admin';
    const ROLE_USER  = 'user';

    /**
     * @param $value
     *
     * @return string
     */
    public static function roleTransformToAvailable($value)
    {
        if (!in_array($value, [static::ROLE_ADMIN, static::ROLE_USER])) {
            $value = static::ROLE_USER;
        }
        return $value;
    }
}

User::setReadTransform('role', 'custom_role', ['callback' => [User::class, 'roleTransformToAvailable']]);
User::setWriteTransform('role', 'custom_role', ['callback' => [User::class, 'roleTransformToAvailable']]);
``` 
This will make any value of `role` field to 'user' if it is not 'user' or 'admin'.
Custom transformations must start from 'custom_' and must contain proper callback.
Callback function first parameter must be $value.

There are some built in transformations like tinyint(1) to bool, datetime to unix timestamp and others. See Transform class.

