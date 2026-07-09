<?php

namespace Sentience\Ai\Schema\Types;

use Sentience\Ai\Schema\Schemable;

abstract class ArrayType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'array';

    public function __construct(protected Schemable|array $items)
    {
    }

    public function schema(): array
    {
        $items = function (Schemable|array $item) use (&$items): mixed {
            if (is_array($item)) {
                if (count($item) === 0) {
                    return [
                        BoolType::SCHEMA_TYPE,
                        IntType::SCHEMA_TYPE,
                        FloatType::SCHEMA_TYPE,
                        StringType::SCHEMA_TYPE,
                        null
                    ];
                }

                return array_map(
                    fn (Schemable|array $item) => $items($item),
                    $item
                );
            }

            if ($item instanceof Schemable) {
                return $item->schema();
            }

            return $item;
        };

        $schema = [
            'type' => $this->nullable ? ['array', 'null'] : 'array',
            'items' => $items($this->items)
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }
}
