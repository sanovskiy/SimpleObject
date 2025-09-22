<?php

namespace Sanovskiy\SimpleObject\Query;

class FilterTypeDetector
{
    const FILTER_TYPE_UNKNOWN = 0b0; // Unknown type
    const FILTER_TYPE_SCALAR = 0b1; // key is not numeric (table column name or SQL expression) and value is scalar
    const FILTER_TYPE_COMPARE_SHORT = 0b10; // key is not numeric and there are two elements in value
    const FILTER_TYPE_COMPARE_LONG = 0b100; // key is numeric and there are three elements in value, and first one is table column name or SQL expression and second is comparison operator
    const FILTER_TYPE_SUB_FILTER = 0b1000; // key is numeric or ':AND' or ':OR'
    const FILTER_TYPE_EXPRESSION = 0b10000; // value is instance of QueryExpression
    const FILTER_TYPE_QUERY_RULE = 0b100000; // value is :ORDER, :LIMIT или :GROUP

    public static function getFilterTypeName($type): string
    {
        $types = [
            self::FILTER_TYPE_UNKNOWN => 'Unknown',
            self::FILTER_TYPE_SCALAR => 'Scalar',
            self::FILTER_TYPE_COMPARE_SHORT => 'CompareShort',
            self::FILTER_TYPE_COMPARE_LONG => 'CompareLong',
            self::FILTER_TYPE_SUB_FILTER => 'SubFilter',
            self::FILTER_TYPE_EXPRESSION => 'Expression',
            self::FILTER_TYPE_QUERY_RULE => 'Query rule',
        ];
        return array_key_exists($type, $types) ? $types[$type] : 'ERROR: Unsupported type';
    }

    public static function detectFilterType($key, $value, $modelClass): int
    {
        return match (true) {
            self::isQueryRule($key) => self::FILTER_TYPE_QUERY_RULE,
            self::isExpression($value) => self::FILTER_TYPE_EXPRESSION,
            self::isCompareShort($key, $value) => self::FILTER_TYPE_COMPARE_SHORT,
            self::isCompareLong($key, $value) => self::FILTER_TYPE_COMPARE_LONG,
            self::isScalar($key, $value, $modelClass) => self::FILTER_TYPE_SCALAR,
            self::isSubFilter($key, $value) => self::FILTER_TYPE_SUB_FILTER,
            default => self::FILTER_TYPE_UNKNOWN
        };
    }

    public static function isQueryRule($key): bool
    {
        return in_array(strtolower($key), [':order', ':limit', ':group']); // Checks if the key represents a query rule
    }

    public static function isExpression($value): bool
    {
        return $value instanceof QueryExpression; // Checks if the value is an instance of QueryExpression
    }

    public static function isScalar($key, $value, $modelClass): bool
    {
        return !is_numeric($key) &&
            !str_starts_with($key, ':') && // Ensures it is not a query rule like :and, :or, :order, :limit, or :group
            ((is_array($value) && static::isIndexedArray($value)) || is_scalar($value) || is_null($value)) && // Checks if the value indexed array (for IN(?)), scalar or null
            (is_null($modelClass) || $modelClass::isTableFieldExist($key)); // Verifies if this is a table field name (checks only if model supplied)
    }

    public static function isCompareShort($key, $value): bool
    {
        return !is_numeric($key) && // key is not numeric
            !str_starts_with($key, ':') && // key is not a query rule
            is_array($value) && // value is an array
            self::isIndexedArray($value) && // value is not an associative array
            is_string($value[0]) && // первый элемент - строка (оператор)
            (
                (count($value) === 2) ||
                (strtolower($value[0]) === 'between' && count($value) === 3)
            ) && // value has exactly two elements OR three elements for BETWEEN
            in_array(strtolower($value[0]), ['=', '!=', '>', '<', '>=', '<=', '<>', 'in', 'not in', 'is', 'is not', 'like', 'between']);
    }

    public static function isCompareLong($key, $value): bool
    {
        return is_numeric($key) && // key is numeric
            !str_starts_with($key, ':') && // key is not a query rule
            is_array($value) && // value is an array
            (is_string($value[0]) || $value[0] instanceof QueryExpression) && // first element is a string (column name) or QueryExpression
            is_string($value[1]) &&
            self::isIndexedArray($value) && // value is not an associative array
            (
                (count($value) === 3) ||
                (strtolower($value[1]) === 'between' && count($value) === 4)
            ) && // value has exactly three elements OR four elements for BETWEEN
            in_array(strtolower($value[1]), ['=', '!=', '>', '<', '>=', '<=', '<>', 'in', 'not in', 'is', 'is not', 'like', 'between']);
    }

    public static function isSubFilter($key, $value): bool
    {
        return
            (
                str_starts_with(strtolower($key), ':and') ||
                str_starts_with(strtolower($key), ':or') // key starts with :and or :or
            ) ||
            (
                is_numeric($key) && // key is numeric
                is_array($value) && // value is an array
                !self::isCompare($key,$value) // this is not a compare record
            );
    }

    public static function isMixedKeysArray(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0 && count(array_filter(array_keys($array), 'is_int')) > 0; // Checks if the array has mixed string and integer keys
    }

    public static function isAssocArray(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    public static function isIndexedArray(array $array): bool
    {
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                return false;
            }
        }
        return true;
    }

    public static function isCompare($key, $value): bool
    {
        return self::isCompareShort($key,$value) || self::isCompareLong($key,$value);
    }
}
