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

    public static function detectFilterType($key, $value,$modelClass): int
    {
        return match (true) {
            self::isQueryRule($key) => self::FILTER_TYPE_QUERY_RULE,
            self::isExpression($value) => self::FILTER_TYPE_EXPRESSION,
            self::isScalar($key, $value,$modelClass) => self::FILTER_TYPE_SCALAR,
            self::isCompareShort($key, $value) => self::FILTER_TYPE_COMPARE_SHORT,
            self::isCompareLong($key, $value) => self::FILTER_TYPE_COMPARE_LONG,
            self::isSubFilter($key, $value) => self::FILTER_TYPE_SUB_FILTER,
            default => self::FILTER_TYPE_UNKNOWN
        };
    }

    private static function isQueryRule($key): bool
    {
        return in_array(strtolower($key), [':order', ':limit', ':group']);
    }

    private static function isExpression($value): bool
    {
        return $value instanceof QueryExpression;
    }

    private static function isScalar($key, $value, $modelClass): bool
    {
        return !is_numeric($key) && !str_starts_with($key, ':') && is_scalar($value) && (is_null($modelClass) || $modelClass::isTableFieldExist($key));
    }

    private static function isCompareShort($key, $value): bool
    {
        return !is_numeric($key) && !str_starts_with($key, ':') && is_array($value) && !self::isMixedKeysArray($value) && count($value) === 2;
    }

    private static function isCompareLong($key, $value): bool
    {
        return is_numeric($key) && !str_starts_with($key, ':') && !self::isMixedKeysArray($value) && is_array($value) && count($value) === 3 && is_string($value[0]) && is_string($value[1]);
    }

    private static function isSubFilter($key, $value): bool
    {
        return str_starts_with($key, ':') || (is_numeric($key) && is_array($value));
    }

    private static function isMixedKeysArray(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0 && count(array_filter(array_keys($array), 'is_int')) > 0;
    }
}
