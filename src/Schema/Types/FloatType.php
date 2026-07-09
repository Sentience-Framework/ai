<?php

namespace Sentience\Ai\Schema\Types;

abstract class FloatType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'float';

    protected null|int|float $minimum = null;
    protected null|int|float $maximum = null;

    public function minimum(float|int $minimum): static
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function maximum(float|int $maximum): static
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function schema(): array
    {
        $schema = ['type' => $this->nullable ? ['number', 'null'] : 'number'];

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
