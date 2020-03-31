<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
abstract class Base
{
    abstract function setValue($key,$value);
    abstract function getValue($key);
    abstract function removeValue($key);
    abstract function hasKey($key);
}