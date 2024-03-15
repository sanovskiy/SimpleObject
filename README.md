# SimpleObject Library Documentation

## Introduction

SimpleObject is a PHP library designed to simplify working with database records by providing an intuitive and easy-to-use interface.

## Installation

You can install SimpleObject via Composer:

```bash
composer require sanovskiy/simple-object
```

## Configuration

To configure SimpleObject, you need to set up database connections. You can define connection settings in your application's configuration file or directly in
your code.

## Usage

SimpleObject allows you to interact with database records using object-oriented models. Here's a basic example of how to use SimpleObject:

```php
// Inserting a new record
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Retrieving records
$users = User::find(['status' => 'active']); // result: QueryResult - immutable version of Collection

// Updating a record
$user = User::one(['id' => 1]); // result: Your\Models\Namespace\Logic\User
$user->name = 'Jane Doe';
$user->save(); // result: bool

// Deleting a record
$user = User::one(['id' => 1]);
$user->delete();
```

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
│  │  ├─ DataTransformerInterface.php        [Interface for data transformers]
│  │  ├─ DateTimeTransformer.php             [Class for transforming datetime data]
│  │  ├─ EnumTransformer.php                 [Class for transforming enum data]
│  │  ├─ FloatTransformer.php                [Class for transforming float data]
│  │  ├─ IntegerTransformer.php              [Class for transforming integer data]
│  │  ├─ JsonTransformer.php                 [Class for transforming JSON data]
│  │  └─ UUIDTransformer.php                 [Class for transforming UUID data]
│  ├─ ModelsWriter                           [Directory containing classes for generating models]
│  │  ├─ ModelsGenerator.php                 [Class for generating models]
│  │  ├─ Parsers                             [Directory containing classes for parsing database structures]
│  │  │  ├─ ParserAbstract.php               [Abstract class for database structure parsers]
│  │  │  ├─ ParserInterface.php              [Interface for database structure parsers]
│  │  │  ├─ ParserMSSQL.php                  [Class for parsing MSSQL database structures]
│  │  │  ├─ ParserMySQL.php                  [Class for parsing MySQL database structures]
│  │  │  ├─ ParserPostgreSQL.php             [Class for parsing PostgreSQL database structures]
│  │  ├─ Schemas                             [Directory containing classes for database schema]
│  │  │  ├─ ColumnSchema.php                 [Class for database table column schema]
│  │  │  └─ TableSchema.php                  [Class for table schema]
│  │  ├─ Writers                             [Directory containing classes for writing models]
│  │  │  ├─ AbstractWriter.php               [Abstract class for model writers]
│  │  │  ├─ Base.php                         [Class for basic model writing]
│  │  │  ├─ Logic.php                        [Class for logic model writing]
│  │  └─ ModelWriterInterface.php            [Interface for model writers]
│  ├─ Query                                  [Directory containing classes for query operations]
│  │  ├─ Filter.php                          [Class for filtering queries]
│  │  └─ QueryExpression.php                 [Class for query expressions]
│  ├─ Relations                              [Directory containing classes for defining relationships]
│  │  ├─ HasMany.php                         [Class for "has many" relationships]
│  │  └─ HasOne.php                          [Class for "has one" relationships]
│  ├─ ActiveRecordAbstract.php               [Abstract class for active record pattern]
│  ├─ ConnectionConfig.php                   [Class for connection configurations]
│  ├─ ConnectionManager.php                  [Class for managing connections]
│  ├─ ExtendedCLIMate.php                    [Class for extending CLIMate library]
│  ├─ Relation.php                           [Class for defining relationships]
│  └─ RuntimeCache.php                       [Class for runtime caching]
├─ .gitignore                                [Git ignore file]
├─ README.md                                 [*This file*]
├─ composer.json                             [Composer configuration file]
└─ composer.lock                             [Composer lock file]
```

## Basic Operations

SimpleObject supports basic CRUD operations (Create, Read, Update, Delete) for interacting with database records.

## Filtering Records

To filter records in the SimpleObject library, you can utilize the Filter class, which allows you to construct SQL queries based on specified conditions. Here's
how you can use the Filter class to build filters for your queries:

```php
// Example of using filters with the find method
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
];
$users = User::find($filters); // Returns QueryResult

// Example of using filters with the one method
$user = User::one(['email' => 'john@example.com', 'status' => 'active']); // returns ?User

// Example of using filters with the count method
$count = User::getCount(['status' => 'active']); // Returns int
```

In the provided example, `$filters` is an associative array where keys represent column names or instructions, and values represent filter conditions. You can
specify multiple conditions using logical operators `:AND` and `:OR`.

The Filter class automatically handles the construction of SQL queries based on the provided filters. Additionally, it provides methods such as getSQL() and
getCountSQL() to retrieve the generated SQL query strings.

Please note that users do not need to instantiate the `Filter` class directly. Instead, they can pass the filters array to the static
methods `::find(array $filterArray)`, `::one(array $filterArray)`, or `::getCount(array $filterArray)` of the relevant model class.

## Advanced Features

- **Custom Data Transformers:** You can create and use your own data transformers to customize data handling.
- **Establishing Relationships:** SimpleObject supports defining relationships between models, such as one-to-many and many-to-one relationships.

## API Reference

For detailed documentation of classes, interfaces, and methods provided by SimpleObject, please refer to the API reference.

## Contributing

We welcome contributions to SimpleObject! If you find any issues or have suggestions for improvements, please feel free to open an issue or submit a pull
request on GitHub.

## License

SimpleObject is distributed under the MIT License. See the LICENSE file for more information.
