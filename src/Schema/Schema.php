<?php

namespace Sentience\Ai\Schema;

use Sentience\Ai\Schema\Types\ArrayType;
use Sentience\Ai\Schema\Types\BoolType;
use Sentience\Ai\Schema\Types\EnumType;
use Sentience\Ai\Schema\Types\FloatType;
use Sentience\Ai\Schema\Types\IntType;
use Sentience\Ai\Schema\Types\ObjectType;
use Sentience\Ai\Schema\Types\Optional\OptionalArrayType;
use Sentience\Ai\Schema\Types\Optional\OptionalBoolType;
use Sentience\Ai\Schema\Types\Optional\OptionalEnumType;
use Sentience\Ai\Schema\Types\Optional\OptionalFloatType;
use Sentience\Ai\Schema\Types\Optional\OptionalIntType;
use Sentience\Ai\Schema\Types\Optional\OptionalObjectType;
use Sentience\Ai\Schema\Types\Optional\OptionalStringType;
use Sentience\Ai\Schema\Types\Required\RequiredArrayType;
use Sentience\Ai\Schema\Types\Required\RequiredBoolType;
use Sentience\Ai\Schema\Types\Required\RequiredEnumType;
use Sentience\Ai\Schema\Types\Required\RequiredFloatType;
use Sentience\Ai\Schema\Types\Required\RequiredIntType;
use Sentience\Ai\Schema\Types\Required\RequiredObjectType;
use Sentience\Ai\Schema\Types\Required\RequiredStringType;
use Sentience\Ai\Schema\Types\StringType;

class Schema
{
    public static function bool(bool $required = true): BoolType
    {
        return $required ? new RequiredBoolType() : new OptionalBoolType();
    }

    public static function int(bool $required = true): IntType
    {
        return $required ? new RequiredIntType() : new OptionalIntType();
    }

    public static function float(bool $required = true): FloatType
    {
        return $required ? new RequiredFloatType() : new OptionalFloatType();
    }

    public static function string(bool $required = true): StringType
    {
        return $required ? new RequiredStringType() : new OptionalStringType();
    }

    public static function enum(array $values, bool $required = true): EnumType
    {
        return $required ? new RequiredEnumType($values) : new OptionalEnumType($values);
    }

    public static function array(Schemable|array $items = [], bool $required = true): ArrayType
    {
        return $required ? new RequiredArrayType($items) : new OptionalArrayType($items);
    }

    public static function object(array $properties, bool $required = true): ObjectType
    {
        return $required ? new RequiredObjectType($properties) : new OptionalObjectType($properties);
    }
}
