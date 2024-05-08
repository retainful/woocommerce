<?php

namespace Rnoc\App\Storage;
abstract class Base
{
    abstract function setValue($key, $value);

    abstract function getValue($key);

    abstract function removeValue($key);

    abstract function hasKey($key);
}