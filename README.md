# SimpleObject 7.x Library Documentation

## Introduction

SimpleObject is a PHP library designed to simplify working with database records by providing an intuitive and easy-to-use interface.

## Installation

You can install SimpleObject via Composer:

```bash
composer require sanovskiy/simple-object
```

## Database Connection Configuration

Before you can start using SimpleObject, you need to configure connections to your database. This involves specifying the database driver, host, user credentials, database name, and other relevant settings. SimpleObject supports MySQL, PostgreSQL, and MSSQL database drivers.

### Adding a Connection

You can add a database connection using the `ConnectionManager` class along with a `ConnectionConfig` object. Below is an example of how to add a MySQL database connection:

```php
use Sanovskiy\SimpleObject\ConnectionManager;
use Sanovskiy\SimpleObject\ConnectionConfig;

ConnectionManager::addConnection(ConnectionConfig::factory([
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'user' => 'sandbox_user',
        'password' => 'letmein',
        'database' => 'sandbox',
        'charset' => 'utf8',
        'port' => '53306'
    ],
    'path_models' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Models',
    'models_namespace' => 'Project\\Models\\',
    'sub_folders_rules' => [
        'acl_' => [
            'folder' => 'Acl' . DIRECTORY_SEPARATOR,
            'strip' => true
        ],
        'shop_' => [
            'folder' => 'Shop' . DIRECTORY_SEPARATOR,
            'strip' => false
        ]
    ],
    'base_class_extends' => 'MyOwnClassThatExtendsActiveRecordAbstract', 
], 'default'));
```

### Explanation of Configuration Parameters

- `driver`: Specifies the database driver (e.g., 'mysql', 'pgsql', 'mssql').
- `host`: Specifies the host of the database server.
- `user`: Specifies the username for connecting to the database.
- `password`: Specifies the password for connecting to the database.
- `database`: Specifies the name of the database.
- `charset`: Specifies the character set for the connection.
- `port`: Specifies the port number (optional).
- `path_models`: Specifies the path where generated models will be stored.
- `models_namespace`: Specifies the namespace for the generated models.
- `sub_folders_rules`: Specifies rules for organizing models into subfolders based on table prefixes (optional).
- `base_class_extends`: Specifies a custom parent class for generated models. Note that this class must extend ActiveRecordAbstract. (optional).
- `read_connection`: Specifies a separate read-only connection (not in use so far).
- `write_connection`: Specifies a separate write-only connection (not in use so far).

### Subfolder Rules Configuration

The `sub_folders_rules` configuration option allows you to organize generated models into subfolders based on table prefixes. This can be useful for structuring your models in a more logical and organized manner, especially in projects with a large number of database tables.

#### Adding Subfolder Rules

You can specify subfolder rules using an associative array where the keys are table prefixes and the values are arrays containing the folder name and a flag indicating whether to strip the prefix from the model class names.

Example:

```php
'sub_folders_rules' => [
    'acl_' => [
        'folder' => 'Acl' . DIRECTORY_SEPARATOR,
        'strip' => true
    ],
    'shop_' => [
        'folder' => 'Shop' . DIRECTORY_SEPARATOR,
        'strip' => false
    ]
]
```

In this example:
- Tables with the prefix 'acl_' will have their models stored in the 'Acl' subfolder.
- Tables with the prefix 'shop_' will have their models stored in the 'Shop' subfolder.
- If `strip` is set to `true`, the prefix will be removed from the model class names. Otherwise, the prefix will be retained.

#### Usage Notes

- Subfolder rules are optional, and you can define them according to your project's specific requirements.
- This feature is particularly useful for projects with a large number of tables or where tables can be logically grouped based on their prefixes.
- Ensure that the folder structure specified in the rules matches your project's namespace conventions and organization preferences.

### Models Generation

After adding connections and whenever there are changes in the database structure, or during the initial setup of your project, you need to call
the `reverseEngineerModels()` method of the `ModelsGenerator` class to generate models based on existing database tables.

#### Ignoring Auto-generated Files

Since the `Base` folder contains auto-generated code, it's recommended to add it to your project's `.gitignore` file to avoid versioning generated files
unnecessarily.

```
/Project/Models/Base/
```

#### Example

Suppose you have tables named `acl_user`, `acl_role`, `shop_product`, and `shop_order`. Using the subfolder rules from the example above would result in the
following folder structure:

```
Project
│
└── Models
    ├── Base
    │   ├── Acl
    │   │   ├── User.php
    │   │   └── Role.php
    │   └── Shop
    │       ├── Product.php
    │       └── Order.php
    └── Logic
        ├── Acl
        │   ├── User.php
        │   └── Role.php
        └── Shop
            ├── Product.php
            └── Order.php
```

This organization helps maintain a clear and organized structure within your project's models directory.


```php
use SimpleObject\ModelsWriter\ModelsGenerator;

ModelsGenerator::reverseEngineerModels();
```

This service method is typically executed during project deployment or after modifications to the database structure. If your code is running in normal operation and the models have
already been generated, there's no need to call this method.

```php
use Sanovskiy\SimpleObject\ModelsWriter\ModelsGenerator;

ModelsGenerator::reverseEngineerModels();
```

This will create models corresponding to your database tables, making them ready for use with SimpleObject.

## Model properties naming and table primary keys
All models and model properties are named in CamelCase by splitting table name or column by underscores.
I.e: field model_id become property ModelId. Column SomeLongFieldName become Somelongfieldname.
Limitation: Table PK should be named `id` to ensure full compatibility with SimpleObject.


## Usage

SimpleObject supports basic CRUD operations (Create, Read, Update, Delete) for interacting with database records.

SimpleObject allows you to interact with database records using object-oriented models. Here's a basic example of how to use SimpleObject:

Model names and their properties precisely mirror the names of corresponding database objects but are converted to CamelCase. However, accessing properties by
their corresponding database field names is permissible. Although this won't result in an error, these properties are not explicitly defined in the base class
as @property and hence may not be recognized by IDEs.

```php
// Inserting a new record
$user = new User();
$user->Name = 'John Doe';
$user->Email = 'john@example.com';
$user->save();

// Instantly update value in database and in model property
$user->store('Name','Jane Doe');
// You also can use table column name
$user->store('email','jane@example.com');

// Retrieving records
$users = User::find(['status' => 'active']); // result: QueryResult - immutable version of Collection

// or just by PK
$user = User::one(['id'=>1]);
// Alternative method
$user = new User();
$user->Id = 1;
$user->load();

// Updating a record
$user = User::one(['id' => 1]); // result: Your\Models\Namespace\Logic\User
$user->Name = 'Jane Doe';
$user->save(); // result: bool

// Deleting a record
$user = User::one(['id' => 1]);
$user->delete();

// Fetching certain orders
$user = Person::one(['id'=>1]);
$userOrders = $user->getShopOrders(); // QueryResult that contain all user's orders
$orders2024 = $user->getShopOrders(['created_at'=>['>','2024-01-01']]); // all user's orders created after 2024-01-01
$last5orders = $user->getShopOrders([':LIMIT'=>5,':ORDER'=>['created_at','DESC']]); // last 5 orders
```

### Filter Class Documentation

The `Filter` class within the SimpleObject library is responsible for constructing SQL queries based on specified conditions. It allows users to filter 
database records effectively. Below is a detailed explanation of its functionality and the format of the filter array used to construct queries.

#### Filter Array Format

The filter array passed to the constructor follows a specific format to define conditions for filtering records. Each element of the array corresponds to a filter condition.

```
$filters = [
    'column_name' => 'value',                     // Simple equality comparison means "where column_name equals 'value'" 
    'column_name' => ['operator', 'value'],       // Comparison with specified operator. I.e. 'column_name'=> ['in',[1,2,3]]
    'column_name' => ['>=' , 18],                 // Example: Greater than or equal comparison
    ':AND' => [                                   // Logical AND group
        'column_name' => 'value',
         ['column_name','operator', 'value']      // Use this method if you need to make several conditions on one field in one level
    ],
    ':OR' => [                                    // Logical OR group
        ['column_name','<','value',]
        ['column_name', '>', 'value']
    ],
    ':ORDER' => ['column_name', 'ASC'],           // Order by clause
    ':LIMIT' => [5],                              // Limit number of records
    ':GROUP' => 'column_name'                     // Group by clause
];
```
The top-level of the filter list is always combined using 'AND' logic.

- **Key-Value Pairs:**
    - Key: Represents the column name in the database table or an instruction.
    - Value:
        - For simple equality comparison: A scalar value.
        - For comparison with specified operator: An array with two elements: the comparison operator and the value.
        - For logical AND or OR groups: An array containing nested filter conditions.
        - For the order by clause: An array with the column name and the sort direction ('ASC' or 'DESC').
        - For the limit clause: An array containing the limit value (and optionally, the offset value).
        - For the group by clause: A string representing the column name.

#### Example Usage

```
$filters = [
    'status' => 'active',
    ':OR' => [
        ':AND' => [
            'age' => ['>=', 18],
            'country' => 'USA'
        ],
        [
            ':AND' => [
                'age' => ['>=', 21],
                'country' => 'Japan'
            ]
        ]
    ],
    ':ORDER' => ['created_at', 'DESC'],
    ':LIMIT' => [10, 5]
];

$filter = new Filter(User::class, $filters);
$sql = $filter->getSQL();        // Retrieve the constructed SQL query
$bind = $filter->getBind();      // Retrieve the bind values for prepared statements
```

- In this example, the filter array defines conditions to retrieve active users from either the USA with an age of 18 or older or from Japan with an age of 21 or older. The results are ordered by the creation date in descending order, and only 5 records are fetched, starting from the 10th record.
- The `Filter` class constructs the SQL query and retrieves the bind values, which can then be used in executing the query.

You typically do not need to directly instantiate the Filter class. Instead, you can pass a filter array to the static methods `::find(array $filterArray)`,
`::one(array $filterArray)`, or `::getCount(array $filterArray`) of the relevant model class."


### Automatic Data Transformation in SimpleObject Library

The SimpleObject Library provides a mechanism for automatic data transformation, which ensures validation and conversion of data when reading from and writing to a database. Below is detailed information about this functionality.

#### Goal

The goal of automatic data transformation is to validate and convert data from the database format to a format convenient for working in PHP, and vice versa. This includes converting date strings to `DateTime` class objects, as well as other data types such as numbers, strings, and JSON.

#### Built-in Data Types

The SimpleObject Library provides built-in transformers for various data types:

- `BooleanTransformer`: Converts values to booleans.
- `DateTimeTransformer`: Converts date strings to `DateTime` class objects.
- `EnumTransformer`: Converts ENUM values to corresponding PHP values.
- `FloatTransformer`: Converts values to floats.
- `IntegerTransformer`: Converts values to integers.
- `JsonTransformer`: Converts JSON strings to PHP arrays or objects.
- `UUIDTransformer`: Converts UUIDs to the appropriate format.

#### Usage

You can add your own custom transformers and bind them to models using the `setDataTransformRule()` method of the model class. For example:

```php
Project\Models\Logic\CustomTable::setDataTransformRule('custom_type_column_name', [
    'propertyType' => 'DateTime',
    'transformerClass' => MyCustomTransformer::class,
    'transformerParams' => ['param' => 'param_value']
]);
```

If a field already has a transformer, it will be overwritten by the new one.

Transformer methods are automatically called when reading from the database or writing to it, ensuring automatic data conversion.

#### Examples

Example of using the built-in `DateTimeTransformer` in a base model:

```php
protected static array $dataTransformRules = [
    'created_at' => [
        'transformerClass' => DateTimeTransformer::class,
        'transformerParams' => ['format' => 'Y-m-d H:i:s'],
    ],
    'updated_at' => [
        'transformerClass' => DateTimeTransformer::class,
        'transformerParams' => ['format' => 'Y-m-d H:i:s'],
    ],
];
```

This code binds the `DateTimeTransformer` transformer to the `created_at` and `updated_at` fields, allowing automatic conversion of date values when
reading and writing to the database.

#### Advantages

- Ensures data validation when reading from and writing to the database.
- Allows conversion of data to a convenient format for working in PHP.
- Provides flexible configuration for binding custom transformers to model fields.

#### Limitations

- Transformers must be properly configured to meet the requirements of your application.
- Additional settings may be required to handle special cases of data transformation.
- Try to avoid adding excessive logic to the methods of your custom transformers, as they are invoked every time data is read from or written to the database.

### Automatic Relationships between Models

Automatic relationships between models in the SimpleObject library are limited to one-to-many and one-to-one relationships. If a table has a defined foreign
key, it will be processed during model generation, and corresponding methods will be created.

#### One-to-Many Relationship

In a one-to-many relationship, if a table has a foreign key pointing to another table, the SimpleObject library automatically generates a method in the
referencing model to retrieve related records.

For example, consider tables `person` and `shop_order`, where `shop_order` has a foreign key `person_id` referencing `person(id)`. In the base model `Person`,
the library automatically generates a method:

```php
public function getShopOrders(?array $filters = []): QueryResult
```

This method retrieves all shop orders associated with the person. You can optionally pass filters to narrow down the result set.

#### One-to-One Relationship

Similarly, in a one-to-one relationship, if a table has a foreign key pointing to another table, the SimpleObject library generates a method in the referenced
model to retrieve the associated record.

Continuing with the example of tables `person` and `shop_order`, suppose `shop_order` has a foreign key `person_id` referencing `person(id)`. In the base
model `ShopOrder`, the library automatically generates a method:

```php
public function getPerson(): ?Person
```

This method retrieves the associated person for the shop order.

#### Usage

These automatically generated methods provide convenient access to related records without the need for manual coding. You can use them to streamline your
database queries and simplify your application logic.

### Note

These automatic relationships are based on foreign key constraints defined in the database schema. Ensure that your database schema accurately reflects the
intended relationships between tables for the SimpleObject library to generate these methods correctly.

### Caching with Runtime Cache

SimpleObject supports caching models data using runtime cache. This means that data is stored only during script execution and is not preserved between
different requests to the application.

#### Caching Implementation

Examples of the `load`, `save`, and `delete` methods in the `ActiveRecordAbstract` class demonstrate caching when performing data operations. Here's how it works:

**Method `load`**: When loading a record from the database, the existence of the record in the cache is checked first. If the record already exists in the
cache, it is retrieved from there, and no database query is executed. If the record is not found in the cache or a forced load is required
(`$forceLoad = true`), then a database query is executed to load the record, and the retrieved data is cached for subsequent use.

**Method `save`**: When saving a record to the database, the data is also stored in the cache. If the record already exists in the database, its data is
updated in both the database and the cache. If the record is inserted into the database for the first time, its data is also added to the cache.

**Method `delete`**: When deleting a record from the database, it is also removed from the cache.

#### Example Usage of Caching

```php
$user = new User();
$user->Id = 1;

// Loads the user from the database if it exists there. If not, it will load data from the database.
$user->load();

$user1 = new User();
$user1->Id = 1;
$user1->load(); // Here, SimpleObject skips querying the database and gets data from the cache.

// If you want to skip cache check, you can pass `true` to this method.
$user1->load(true); // Force load from the database
```

#### Important Note

- Caching with runtime cache in SimpleObject provides efficient cache management to enhance your application's performance.
- Applying caching is recommended for data operations that are frequently performed and can be cached for reuse.

## Contributing

We welcome contributions to SimpleObject! If you find any issues or have suggestions for improvements, please feel free to open an issue or submit a pull
request on GitHub.

## Thanks

Special thanks to ChatGPT for help with routine tasks.

## License

SimpleObject is distributed under the MIT License with Custom Conditions. See the LICENSE file for more information.

## Directory structure

```
Directory structure:
├─ src
│  ├─ Collections                            [Directory containing classes for work with collections]
│  │  ├─ Collection.php                      [Class for working with collections of objects]
│  │  ├─ ImmutableCollection.php             [Class for immutable collections of objects. Extends Collection]
│  │  └─ QueryResult.php                     [Class for query results. Extends ImmutableCollection]
│  ├─ DataTransformers                       [Directory containing classes for data transformation]
│  │  ├─ BooleanTransformer.php              [Class for transforming boolean data]
│  │  ├─ DataTransformerAbstract.php         [Abstract class for data transformers]
│  │  ├─ DateTimeTransformer.php             [Class for transforming datetime data]
│  │  ├─ EnumTransformer.php                 [Class for transforming enum data]
│  │  ├─ FloatTransformer.php                [Class for transforming float data]
│  │  ├─ IntegerTransformer.php              [Class for transforming integer data]
│  │  ├─ JsonTransformer.php                 [Class for transforming JSON data]
│  │  └─ UUIDTransformer.php                 [Class for transforming UUID data]
│  ├─ Interfaces                             [Directory containing interfaces]
│  │  ├─ DataTransformerInterface.php        [Interface for data transformers]
│  │  ├─ ModelWriterInterface.php            [Interface for model writers]
│  │  └─ ParserInterface.php                 [Interface for database structure parsers]
│  ├─ ModelsWriter                           [Directory containing classes for generating models]
│  │  ├─ ModelsGenerator.php                 [Class for generating models]
│  │  ├─ Parsers                             [Directory containing classes for parsing database structures]
│  │  │  ├─ ParserAbstract.php               [Abstract class for database structure parsers]
│  │  │  ├─ ParserMSSQL.php                  [Class for parsing MSSQL database structures]
│  │  │  ├─ ParserMySQL.php                  [Class for parsing MySQL database structures]
│  │  │  ├─ ParserPostgreSQL.php             [Class for parsing PostgreSQL database structures]
│  │  ├─ Schemas                             [Directory containing classes for database schema]
│  │  │  ├─ ColumnSchema.php                 [Class for database table column schema]
│  │  │  └─ TableSchema.php                  [Class for table schema]
│  │  └─ Writers                             [Directory containing classes for writing models]
│  │     ├─ AbstractWriter.php               [Abstract class for model writers]
│  │     ├─ Base.php                         [Class for basic model writing]
│  │     └─ Logic.php                        [Class for logic model writing]
│  ├─ Query                                  [Directory containing classes for query operations]
│  │  ├─ Filter.php                          [Class for filtering queries]
│  │  ├─ FilterTypeDetector.php              [Class for query part type detection]
│  │  └─ QueryExpression.php                 [Class for query expressions]
│  ├─ Traits                                 [Directory containing traits]
│  │  └─ ActiveRecordIteratorTrait.php       [Trait for ActiveRecordAbstract with implementation of Iterator, ArrayAccess and Countable interfaces]
│  ├─ ActiveRecordAbstract.php               [Abstract class for active record pattern]
│  ├─ ConnectionConfig.php                   [Class for connection configurations]
│  ├─ ConnectionManager.php                  [Class for managing connections]
│  ├─ ExtendedCLIMate.php                    [Class for extending CLIMate library]
│  └─ RuntimeCache.php                       [Class for runtime caching]
├─ .gitignore                                [Git ignore file]
├─ README.md                                 [*This file*]
└─ composer.json                             [Composer configuration file]
```
