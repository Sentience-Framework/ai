<?php

namespace Sentience\Ai\Schema\Types;

use Sentience\Ai\Schema\Schemable;
use Sentience\Ai\Schema\Types\Required\RequiredType;

abstract class ObjectType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'object';

    public function __construct(protected array $properties)
    {
    }

    public function schema(): array
    {
        $schema = [
            'type' => $this->nullable ? ['object', 'null'] : 'object',
            'properties' => (object) array_map(
                fn (Schemable $type): array => $type->schema(),
                $this->properties
            ),
            'required' => array_keys(
                array_filter(
                    $this->properties,
                    fn (TypeAbstract $type): bool => $type instanceof RequiredType
                )
            )
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }
}
