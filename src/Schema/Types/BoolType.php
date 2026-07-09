<?php

namespace Sentience\Ai\Schema\Types;

abstract class BoolType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'boolean';

    public function schema(): array
    {
        $schema = ['type' => $this->nullable ? ['boolean', 'null'] : 'boolean'];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }
}
