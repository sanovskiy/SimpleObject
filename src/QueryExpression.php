<?php

namespace Sanovskiy\SimpleObject;

class QueryExpression
{
    private string $functionName;
    private array $arguments;

    public function __construct(string $functionName, array $arguments = [])
    {
        if (!self::isValidFunctionName($functionName)) {
            throw new \RuntimeException($functionName . 'is not an allowed function name');
        }
        $this->functionName = strtolower($functionName);
        $this->arguments = array_map(function ($string) {
            if (!str_starts_with($string, '\'') && !str_ends_with($string, '\'')) {
                return '\'' . $string . '\'';
            }
            return $string;
        }, $arguments);
    }

    public static function isValidFunctionName($name): bool
    {
        return in_array(strtolower($name), [
            'year',
            'month',
            'day',
            'sum',
            'min',
            'max',
            'count',
            'avg',
            'sum',
            'upper',
            'lower',
            'concat',
            'substring',
            'left',
            'right',
            'trim',
            'round',
            'now',
            'ifnull',
            'coalesce',
        ]);
    }

    public function getString(): string
    {
        return (string)$this;
    }

    public function __toString()
    {
        return strtoupper($this->functionName) . '(' . implode(',', $this->arguments) . ')';
    }
}