<?php

namespace Sanovskiy\SimpleObject;

use ArrayAccess;
use Countable;
use Error;
use Exception;
use InvalidArgumentException;
use Iterator;
use PDO;
use PDOStatement;
use RuntimeException;
use Sanovskiy\SimpleObject\Collections\QueryResult;
use Sanovskiy\SimpleObject\Query\Filter;
use Sanovskiy\SimpleObject\Traits\ActiveRecordIteratorTrait;
use Sanovskiy\Utility\NamingStyle;

/**
 * Abstract class providing basic functionality for ActiveRecord pattern.
 *
 * This class serves as a base for implementing ActiveRecord pattern in PHP.
 * It provides methods for interacting with database records using object-oriented models.
 * Subclasses must implement specific database table mappings and additional business logic.
 *
 * @property int $Id Identity
 * @property-read PDO $ReadConnection The PDO connection for reading operations.
 * @property-read PDO $WriteConnection The PDO connection for writing operations.
 * @property-read string $TableName The name of the database table associated with the ActiveRecord.
 * @property-read array $TableFields An array containing the names of all fields in the database table.
 * @property-read array $Properties An array containing the names of all properties in the ActiveRecord.
 * @property-read string $SimpleObjectConfigNameRead The name of the configuration for reading operations.
 * @property-read string $SimpleObjectConfigNameWrite The name of the configuration for writing operations.
 */
abstract class ActiveRecordAbstract implements Iterator, ArrayAccess, Countable
{
    use ActiveRecordIteratorTrait;

    /**
     * Configuration name for the database read connection.
     * @var string
     */
    protected static string $SimpleObjectConfigNameRead = 'default';

    /**
     * Configuration name for the database write connection.
     * @var string
     */
    protected static string $SimpleObjectConfigNameWrite = 'default';

    /**
     * Name of the database table associated with the ActiveRecord.
     * @var string
     */
    protected static string $TableName;
    protected static ?string $TablePK = null;
    /**
     * Mapping of property names to database table fields.
     * @var array ['table_field'=>'TableField']
     */
    protected static array $propertiesMapping;

    /**
     * Rules for transforming data before saving to or after retrieving from the database.
     * @var TransformRule[]
     */
    protected static array $dataTransformRules;

    /**
     * Values of properties of the ActiveRecord.
     * @var array
     */
    private array $values = [];

    /**
     * Loaded values of the ActiveRecord retrieved from the database.
     * @var array|null
     */
    private ?array $loadedValues = null;

    /**
     * Indicates whether the ActiveRecord has been deleted from the database.
     * @var bool
     */
    protected bool $isDeleted = false;

    /**
     * int $id as __construct param was removed in version 7
     */
    final public function __construct()
    {
        if (empty(static::$TableName)) {
            throw new RuntimeException(
                static::class . ' has no defined table name. Possible misconfiguration. Try to regenerate base models'
            );
        }
        if (!$this->init()) {
            throw new RuntimeException('Model ' . static::class . '::init() failed');
        }
    }

    /**
     * Initializes the object.
     *
     * This method is called during object construction and can be overridden in subclasses
     * to perform any necessary initialization logic.
     *
     * @return bool True if initialization was successful, false otherwise.
     */
    protected function init(): bool
    {
        // This method called in __construct() and can be overloaded in children
        return true;
    }

    /**
     * Retrieves config name for reading connection
     * @return string
     */
    public static function getSimpleObjectConfigNameRead(): string
    {
        return static::$SimpleObjectConfigNameRead;
    }

    /**
     * Retrieves config name for writing connection
     * @return string
     */
    public static function getSimpleObjectConfigNameWrite(): string
    {
        return static::$SimpleObjectConfigNameWrite;
    }

    /**
     * Retrieves the raw values stored in the current instance of the ActiveRecord.
     *
     * This method returns the raw values stored in the object, which may include values
     * that have been modified but not yet saved to the database.
     *
     * @return array An associative array representing the raw values stored in the object.
     */
    public function getRawValues(): array
    {
        return $this->values;
    }

    /**
     * Retrieves the loaded values of the current instance of the ActiveRecord.
     *
     * This method returns the values that were loaded from the database when the object
     * was initially fetched. It provides a snapshot of the object's state at the time
     * of retrieval.
     *
     * @return array|null An associative array representing the loaded values of the object,
     *                     or null if the object has not been loaded from the database.
     */
    public function getLoadedValues(): ?array
    {
        return $this->loadedValues;
    }

    /**
     * Retrieves the PDO connection for reading operations.
     *
     * This method returns the PDO connection configured for reading operations.
     *
     * @return PDO The PDO connection for reading operations.
     */
    public static function getReadConnection(): PDO
    {
        $c = ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead);
        $c->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        return $c;
    }

    /**
     * Retrieves the PDO connection for writing operations.
     *
     * This method returns the PDO connection configured for writing operations.
     *
     * @return PDO The PDO connection for writing operations.
     */
    public static function getWriteConnection(): PDO
    {
        $c = ConnectionManager::getConnection(static::$SimpleObjectConfigNameWrite);
        $c->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        return $c;
    }

    /**
     * Retrieves the name of the table associated with the ActiveRecord.
     *
     * This method returns the name of the database table associated with the current ActiveRecord class.
     *
     * @return string The name of the table associated with the ActiveRecord.
     */
    public static function getTableName(): string
    {
        return static::$TableName;
    }

    /**
     * Retrieves the fields of the table associated with the ActiveRecord.
     *
     * This method returns an array containing the names of the fields in the database table
     * associated with the current ActiveRecord class.
     *
     * @return array An array containing the names of the fields of the table associated with the ActiveRecord.
     */
    public static function getTableFields(): array
    {
        return array_keys(static::$propertiesMapping);
    }

    /**
     * Retrieves the name of the property representing the primary key of the ActiveRecord.
     *
     * This method returns the name of the property representing the primary key of the ActiveRecord.
     *
     * @return string The name of the property representing the primary key.
     */
    public function getIdProperty(): string
    {
        if (!is_null(static::$TablePK)){
            return static::$propertiesMapping[static::$TablePK];
        }
        return array_values(static::$propertiesMapping)[0];
    }

    /**
     * Retrieves the name of the field representing the primary key of the table associated with the ActiveRecord.
     *
     * This method returns the name of the field representing the primary key of the database table
     * associated with the current ActiveRecord class.
     *
     * @return string The name of the field representing the primary key.
     */
    protected function getIdField(): string
    {
        if (!is_null(static::$TablePK)){
            return static::$TablePK;
        }
        return static::getTableFields()[0];
    }

    /**
     * Checks if a property exists in the model.
     *
     * This method checks if the specified property exists in the model.
     *
     * @param mixed $name The name of the property to check.
     * @return bool True if the property exists, false otherwise.
     */
    public static function isPropertyExist(mixed $name): bool
    {
        return in_array($name, static::$propertiesMapping, true);
    }

    /**
     * Checks if a table field exists in the model.
     *
     * This method checks if the specified table field exists in the model.
     *
     * @param string $name The name of the table field to check.
     * @return bool True if the table field exists, false otherwise.
     */
    public static function isTableFieldExist(string $name): bool
    {
        return array_key_exists($name, static::$propertiesMapping);
    }

    /**
     * Retrieves the property corresponding to a table field.
     *
     * This method retrieves the name of the property corresponding to the specified table field.
     *
     * @param string $propertyName The name of the property.
     * @return string The name of the corresponding table field.
     * @throws Error If the property does not exist in the model.
     */
    public static function getPropertyField(string $propertyName): string
    {
        if (!static::isPropertyExist($propertyName)) {
            throw new Error('Property ' . $propertyName . ' not exist im model ' . static::class);
        }
        return array_flip(static::$propertiesMapping)[$propertyName];
    }

    /**
     * Retrieves the table field corresponding to a property.
     *
     * This method retrieves the name of the table field corresponding to the specified property.
     *
     * @param string $tableFieldName The name of the table field.
     * @return string The name of the corresponding property.
     * @throws RuntimeException If the table field does not exist in the model.
     */
    public function getFieldProperty(string $tableFieldName): string
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new RuntimeException('Table field ' . $tableFieldName . ' not exist im model ' . static::class);
        }
        return static::$propertiesMapping[$tableFieldName];
    }

    /**
     * Checks if the current instance exists in the storage (e.g., database).
     *
     * This method checks if the current instance exists in the storage (e.g., database).
     * Optionally, you can force a check in the database by setting $forceCheck to true.
     *
     * @param bool $forceCheck Whether to force a check in the database. Default is false.
     * @return bool True if the instance exists in the storage, false otherwise.
     */
    public function isExistInStorage(bool $forceCheck = false): bool
    {
        if ($forceCheck) {
            return static::getCount(['id' => $this->{static::getIdField()}]) > 0;
        }
        return !empty($this->loadedValues);
    }

    private bool $skipPopulateCaching = false;

    /**
     * Loads data of the object from the database.
     *
     * This method loads data of the object from the database based on the primary key.
     * If $forceLoad is true or the data is not found in the runtime cache, a query
     * is executed to fetch the data from the database.
     *
     * @param bool $forceLoad Whether to force loading data from the database even if it is available in the runtime cache. Default is false.
     * @return bool True if the data is loaded successfully, false otherwise.
     * @throws RuntimeException If an error occurs while fetching data from the database.
     */
    protected function load(bool $forceLoad = false): bool
    {
        if (!$forceLoad && $this->isDeleted) {
            throw new RuntimeException('Cannot load a deleted object.');
        }
        if (null === $this->{$this->getIdProperty()}) {
            return false;
        }
        if ($forceLoad || !($result = RuntimeCache::getInstance()->get(static::class, $this->{$this->getIdProperty()}))) {
            try {
                $query = sprintf("SELECT * FROM %s WHERE %s = ?", static::getTableName(), $this->getIdField());
                $db = static::getReadConnection();
                $statement = $db->prepare($query);

                if (!$statement->execute([$this->{$this->getIdProperty()}])) {
                    throw new RuntimeException('Fetch by PK failed: ' . $statement->errorInfo()[2]);
                }
                if ($statement->rowCount() < 1) {
                    $this->loadedValues = [];
                    return false;
                }
                $result = $statement->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
            }

            RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $result);
            $this->skipPopulateCaching = true;
        }
        $this->populate($result);
        return true;
    }

    /**
     * Populates the object with data from the provided array.
     *
     * This method populates the object with data from the provided array,
     * which can be either from the database or from any other source.
     * It applies transformations to the values (if specified), sets the
     * values of object properties accordingly, and updates the loaded values
     * and the runtime cache if the object is not a new record.
     *
     * @param array $data An associative array containing data to populate the object.
     * @param bool $applyTransforms Whether to apply transformations to the data. Default is true.
     * @param bool $isNewRecord Whether the object is a new record. Default is false.
     * @throws InvalidArgumentException If bad data is provided for any property.
     */
    public function populate(array $data, bool $applyTransforms = true, bool $isNewRecord = false)
    {
        foreach ($data as $tableFieldName => $value) {
            if (!static::isTableFieldExist($tableFieldName)) {
                continue;
            }

            $rule = $this->getTransformRuleForField($tableFieldName);

            if (!empty($rule)) {
                if ($applyTransforms) {
                    $value = $rule->toProperty($value);
                }
                if (!$rule->isValidPropertyData($value)) {
                    throw new InvalidArgumentException('Bad data for property ' . NamingStyle::toCamelCase($tableFieldName, true));
                }
            }

            $propertyName = static::getFieldProperty($tableFieldName);
            $this->{$propertyName} = $value;
        }
        if (!$isNewRecord) {
            $this->loadedValues = $data;
            if (!$this->skipPopulateCaching) {
                RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $data);
                $this->skipPopulateCaching = false;
            }
        }
    }

    /**
     * Sets data transformation rules for a specified column.
     *
     * This method sets data transformation rules for the specified column.
     * It validates the existence of the column in the table and the validity
     * of the provided transformer class.
     *
     * @param string $columnName The name of the column.
     * @param array $transformRule An array containing transformation rules, including the transformer class.
     * @return bool True if the data transformation rules are set successfully, false otherwise.
     * @throws InvalidArgumentException If the column does not exist in the table or the transformer class is invalid.
     */
    protected static function setDataTransformRuleForField(string $columnName, array $transformRule): bool
    {
        if (!static::isTableFieldExist($columnName)) {
            throw new InvalidArgumentException('Column ' . $columnName . ' does not exist in table ' . self::getTableName());
        }
        static::$dataTransformRules[$columnName] = new TransformRule($transformRule['transformerClass'], $transformRule['transformerParams'] ?? null, $transformRule['propertyType'] ?? null);;
        return true;
    }

    /**
     * Retrieves the data transformer for a specified field.
     *
     * This method retrieves the data transformer for the specified field,
     * if one is configured.
     *
     * @param string $tableFieldName The name of the table field.
     * @return TransformRule|null TransformRule class or null if no transformer is configured.
     * @throws InvalidArgumentException If the column does not exist in the table.
     * @throws InvalidArgumentException If the configured transformer class does not exist or does not implement DataTransformerInterface.
     */
    protected function getTransformRuleForField(string $tableFieldName): ?TransformRule
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new InvalidArgumentException('Column ' . $tableFieldName . ' does not exist in table ' . static::getTableName());
        }

        if (empty(static::$dataTransformRules[$tableFieldName]) || !(static::$dataTransformRules[$tableFieldName] instanceof TransformRule)) {
            return null;
        }

        return static::$dataTransformRules[$tableFieldName];
    }

    /**
     * Factory method for creating QueryResult objects based on SQL statement or PDOStatement.
     *
     * @param PDOStatement|string $statement The SQL statement or PDOStatement.
     * @param array $bind An array of parameters to bind to the prepared statement.
     * @return QueryResult A QueryResult object containing the results of the query.
     * @throws InvalidArgumentException If the provided statement is not a string or PDOStatement.
     * @throws RuntimeException If an error occurs during query execution or object creation.
     */
    public static function factory(PDOStatement|string $statement, array $bind = []): QueryResult
    {
        if (!is_string($statement) && !($statement instanceof PDOStatement)) {
            throw new RuntimeException(sprintf('Unknown type %s. Expected string or PDOStatement', gettype($statement)));
        }
        if (is_string($statement)) {
            $statement = ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead)->prepare($statement);
        }

        $statement->execute($bind);

        $data = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), static::getTableFields())) > 0) {
                throw new RuntimeException('Missing fields ' . implode(', ', $missingFields));
            }
            $entity = new static();
            $entity->populate($row);
            $data[] = $entity;
        }
        return new QueryResult($data, null, $statement);
    }

    /**
     * Finds records in the database based on the specified conditions.
     *
     * This method constructs a query using the provided conditions and retrieves records
     * from the database that match these conditions. It returns a QueryResult object
     * containing the found records.
     *
     * @param array $conditions An associative array of conditions for the query.
     * @return QueryResult A QueryResult object containing the records found in the database.
     * @see https://github.com/sanovskiy/SimpleObject
     */
    public static function find(array $conditions): QueryResult
    {
        $query = new Filter($conditions, static::class);
        $sql = $query->getSQL();
        $bind = $query->getBind();

        return static::factory($sql, $bind);
    }

    /**
     * Retrieves a single record from the database based on the specified conditions.
     *
     * This method constructs a query using the provided conditions and retrieves a single record
     * from the database that matches these conditions. It returns the found record as an instance
     * of the calling class, or null if no record is found.
     *
     * @param array $conditions An associative array of conditions for the query.
     * @return static|null An instance of the calling class representing the found record, or null if no record is found.
     * @see find()
     */
    public static function one(array $conditions): ?static
    {
        return static::find($conditions)->getElement();
    }

    /**
     * Retrieves the count of records from the database based on the specified conditions.
     *
     * This method constructs a query using the provided conditions and retrieves the count
     * of records from the database that match these conditions. It returns the count as an integer.
     *
     * @param array $conditions An associative array of conditions for the query.
     * @return int The count of records matching the specified conditions.
     */
    public static function getCount(array $conditions): int
    {
        $query = new Filter($conditions, static::class);
        $stmt = self::getReadConnection()->prepare($query->getCountSQL());
        $stmt->execute($query->getCountBind());
        return (int)$stmt->fetchColumn();
    }


    /**
     * Retrieves the data to be saved in the database for the current instance of the ActiveRecord.
     *
     * This method iterates through the properties mapping of the ActiveRecord class and retrieves
     * the values of each property. Optionally, it applies transformers to transform the values
     * before saving to the database.
     *
     * @param bool $applyTransforms Whether to apply transformers to the data. Default is true.
     * @return array|null An associative array representing the data to be saved, or null if no data is available.
     */
    public function getDataForSave(bool $applyTransforms = true): ?array
    {
        $data = [];
        foreach (array_keys(static::$propertiesMapping) as $tableFieldName) {
            $value = null;
            $property = static::getFieldProperty($tableFieldName);

            if (isset($this->values[$property])) {
                $value = $this->values[$property];
            }

            $rule = $this->getTransformRuleForField($tableFieldName);
            if ($applyTransforms && $value !== null && !empty($rule)) {
                $value = $rule->toDatabaseValue($value);
            }

            // Make sure null values are not added to the data array
            if ($value !== null) {
                $data[$tableFieldName] = $value;
            }
        }

        return $data;
    }

    /**
     * Checks if there are any changes made to the current instance of the ActiveRecord.
     *
     * This method compares the current values of the object's properties with the values that
     * would be stored in the database upon saving. If any differences are found, it indicates
     * that there are changes.
     *
     * @return bool True if there are changes, false otherwise.
     */
    public function hasChanges(): bool
    {
        // Get the data for saving in the format it will be stored in the database
        $dataForSave = $this->getDataForSave();

        // If there is no data for saving, consider there are no changes
        if (empty($dataForSave)) {
            return false;
        }

        // Check if there are differing fields between the current values and the data for saving
        $differingFields = array_diff_assoc($dataForSave, $this->loadedValues);
        // If there are differing fields, there are changes
        return !empty($differingFields);
    }

    /**
     * Instantly updates the value in the database and in the model property.
     *
     * This method sets the specified value to the given property, updates the corresponding field in the database,
     * and updates the loaded values and the runtime cache accordingly.
     *
     * @param string $propertyOrField The name of the property or field to update.
     * @param mixed $value The new value to set.
     * @throws InvalidArgumentException If the specified property or field doesn't exist in the model.
     * @throws RuntimeException If failed to save record.
     */
    public function store(string $propertyOrField, mixed $value): void
    {
        if (!static::isPropertyExist($propertyOrField) && !static::isTableFieldExist($propertyOrField)) {
            throw new InvalidArgumentException(sprintf("Property of field %s doesn't exist in this model", $propertyOrField));
        }
        $propertyName = static::isPropertyExist($propertyOrField) ? $propertyOrField : null;
        $fieldName = static::isTableFieldExist($propertyOrField) ? $propertyOrField : null;
        $propertyName = $propertyName ?? static::getFieldProperty($fieldName);
        $fieldName = $fieldName ?? static::getPropertyField($propertyName);

        $rule = $this->getTransformRuleForField($fieldName);
        $DBValue = $propertyValue = $value;
        if (!empty($rule)) {
            if ($rule->isValidDatabaseData($value)) {
                $propertyValue = $rule->toProperty($value);
            } elseif ($rule->isValidPropertyData($value)) {
                $DBValue = $rule->toDatabaseValue($value);
            }
        }
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            static::getTableName(),
            $fieldName . ' = ?',
            $this->getIdField()
        );
        $bind = [$DBValue, $this->{$this->getIdField()}];
        $db = static::getWriteConnection();
        $stmt = $db->prepare($sql);
        if (!$stmt->execute($bind)) {
            throw new RuntimeException('Failed to save record: ' . $stmt->errorInfo()[2]);
        }
        $this->{$propertyName} = $propertyValue;
        $this->loadedValues[$fieldName] = $DBValue;
        RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $this->values);
    }

    /**
     * Saves the current state of the object in the database.
     *
     * returns true if the save operation is successful, and false otherwise.
     *
     * @param bool $force Whether to force the update operation even if there are no changes in the object's state.
     * @return bool True if the save operation is successful, false otherwise.
     * @throws RuntimeException If the save operation fails due to an error in the database or other reasons.
     */
    public function save(bool $force = false): bool
    {
        if ($this->isDeleted) {
            throw new RuntimeException('Cannot save a deleted object.');
        }
        try {
            // Get data for saving
            $data = $this->getDataForSave();

            // If there is no data to save, return false
            if (empty($data)) {
                return false;
            }

            // Check if updating record in the database is needed
            if ($this->isExistInStorage()) {
                // Check if there are differing fields between current values and loaded values
                if (!$force && !$this->hasChanges()) {
                    // No differing fields, so just return true without updating
                    return true;
                }

                // Update existing record
                $fields = array_keys($data);
                $updateFields = array_map(fn($field) => "$field = ?", $fields);
                $values = array_values($data);
                $values[] = $this->{$this->getIdProperty()};
                $sql = sprintf(
                    "UPDATE %s SET %s WHERE %s = ?",
                    static::getTableName(),
                    implode(', ', $updateFields),
                    $this->getIdField()
                );
            } else {
                // Insert new record
                $fields = array_keys($data);
                $placeholders = array_fill(0, count($fields), '?');
                $values = array_values($data);
                $sql = sprintf(
                    "INSERT INTO %s (%s) VALUES (%s)",
                    static::getTableName(),
                    implode(', ', $fields),
                    implode(', ', $placeholders)
                );
            }

            // Execute the query
            $db = static::getWriteConnection();
            $stmt = $db->prepare($sql);
            if (!$stmt->execute($values)) {
                throw new RuntimeException('Failed to save record: ' . $stmt->errorInfo()[2]);
            }

            // Update cache if a new record is created
            if (!$this->isExistInStorage()) {
                $id = $db->lastInsertId();
                $this->{$this->getIdProperty()} = $id;
                RuntimeCache::getInstance()->put(static::class, $id, $data);
            } else {
                // Update cache of loaded values
                RuntimeCache::getInstance()->put(static::class, $this->{$this->getIdProperty()}, $data);
            }

            $this->loadedValues = $data;
            return true;
        } catch (Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Deletes the current record from the database.
     *
     * This method deletes the current record from the database if it exists.
     * It returns true if the delete operation is successful, and false otherwise.
     *
     * @return bool True if the delete operation is successful, false otherwise.
     * @throws RuntimeException If the delete operation fails due to an error in the database or other reasons.
     */
    public function delete(): bool
    {
        if (!$this->isExistInStorage()) {
            return false;
        }

        try {
            $sql = sprintf(
                "DELETE FROM %s WHERE %s = ?",
                static::getTableName(),
                $this->getIdField()
            );
            $db = static::getWriteConnection();
            $stmt = $db->prepare($sql);
            if (!$stmt->execute([$this->{$this->getIdProperty()}])) {
                throw new RuntimeException('Failed to delete record: ' . $stmt->errorInfo()[2]);
            }

            $this->Id = null;
            // Mark the object as deleted to prevent further actions
            $this->isDeleted = true;
            // Clear loaded values and remove from cache
            RuntimeCache::getInstance()->drop(static::class, $this->{$this->getIdField()});
            $this->loadedValues = [];
            return true;
        } catch (Exception $e) {
            throw new RuntimeException('SimpleObject error: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function __set(string $name, mixed $value)
    {
        if (static::isPropertyExist($name) || static::isTableFieldExist($name)) {
            $propertyName = static::isPropertyExist($name) ? $name : null;
            $fieldName = static::isTableFieldExist($name) ? $name : null;
            $propertyName = $propertyName ?? static::getFieldProperty($fieldName);
            $fieldName = $fieldName ?? static::getPropertyField($propertyName);

            $rule = $this->getTransformRuleForField($fieldName);
            if (!empty($rule)) {
                if (
                    !$rule->isValidPropertyData($value) &&
                    $rule->isValidDatabaseData($value)
                ) {
                    $value = $rule->toProperty($value);
                }
                if (!$rule->isValidPropertyData($value)) {
                    if ($fieldName !== 'id' || (!is_numeric($value) && !is_null($value))) {
                        throw new InvalidArgumentException(sprintf('Bad data for property %s %s', $name, is_object($value) ? get_class($value) : gettype($value)));
                    }
                }
            }
            $this->values[$propertyName] = $value;
        }
    }

    public function __isset($name): bool
    {
        if (static::isPropertyExist($name) || static::isTableFieldExist($name)) {
            return true;
        }

        return match ($name) {
            'ReadConnection', 'WriteConnection', 'TableName', 'TableFields',
            'Properties', 'SimpleObjectConfigNameRead', 'SimpleObjectConfigNameWrite' => true,
            default => false,
        };
    }

    public function __get(string $name): mixed
    {
        if (static::isPropertyExist($name) || static::isTableFieldExist($name)) {
            $propertyName = static::isPropertyExist($name) ? $name : null;
            $fieldName = static::isTableFieldExist($name) ? $name : null;
            $fieldName = $fieldName ?? static::getPropertyField($propertyName);
            $propertyName = $propertyName ?? static::getFieldProperty($fieldName);
            if (array_key_exists($propertyName, $this->values)) {
                return $this->values[$propertyName];
            }
        }

        return match ($name) {
            'ReadConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameRead),
            'WriteConnection' => ConnectionManager::getConnection(static::$SimpleObjectConfigNameWrite),
            'TableName' => static::$TableName,
            'TableFields' => static::getTableFields(),
            'Properties' => array_values(static::$propertiesMapping),
            'SimpleObjectConfigNameRead' => static::$SimpleObjectConfigNameRead,
            'SimpleObjectConfigNameWrite' => static::$SimpleObjectConfigNameWrite,
            default => null,
        };
    }

    /**
     * Convert the object to an array.
     *
     * This method converts the object's properties to an associative array
     * where keys are property names and values are corresponding property values.
     *
     * @return array An associative array representation of the object.
     */
    public function __toArray(): array
    {
        $result = [];
        foreach ($this->values as $tableFieldName => $value) {
            if (!$propertyName = $this->getFieldProperty($tableFieldName)) {
                continue;
            }
            $result[$propertyName] = $value;
        }
        return $result;
    }
}
