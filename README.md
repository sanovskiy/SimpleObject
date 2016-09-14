# SimpleObject

Get SimpleObject: https://packagist.org/packages/sanovskiy/simple-object

# How to generate models
```PHP
require 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$config =  [
            'dbcon'       => [
                'host'     => 'localhost',
                'user'     => 'user',
                'password' => 'password',
                'database' => 'my_database',
            ],
            'path_models' => __DIR__.'/Models/default',
           ];
SimpleObject::init($config);
SimpleObject::reverseEngineerModels();
```
# About PKs
Don't forget that table PK must be THE FIRST column in the table.

# Model properties naming

All models and model properties properties are named in CamelCase by splitting table name or column by underscores.
I.e: field model_id become property ModelId. Column SomeLongFieldName bacome Somelongfieldname.


