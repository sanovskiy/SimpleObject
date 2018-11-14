# SimpleObject

## Installation
``` composer require sanovskiy/simple-object```

## How to generate models
See cli/generate-models-example.php

## About PKs
Don't forget that table PK must be named `id`

# Model properties naming

All models and model properties properties are named in CamelCase by splitting table name or column by underscores.
I.e: field model_id become property ModelId. Column SomeLongFieldName become Somelongfieldname.

Use robmorgan/phinx (https://github.com/cakephp/phinx) to make fully supported tables


