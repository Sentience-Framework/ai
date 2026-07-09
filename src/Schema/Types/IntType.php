<?php

namespace Sentience\Ai\Schema\Types;

abstract class IntType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'int';

    protected ?int $minimum = null;
    protected ?int $maximum = null;

    public function minimum(int $minimum): static
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function maximum(int $maximum): static
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function schema(): array
    {
        $schema = ['type' => $this->nullable ? ['integer', 'null'] : 'integer'];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        return $schema;
    }
}
