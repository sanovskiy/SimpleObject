<?php

namespace Sanovskiy\SimpleObject\FieldValues;

use \Stringable;

class NullValue implements Stringable
{

    public function __toString(): string
    {
        return '';
    }
}