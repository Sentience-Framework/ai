<?php

namespace Sentience\Ai\Schema\Types;

use Sentience\Ai\Schema\Schemable;

abstract class TypeAbstract implements Schemable
{
    protected bool $nullable = false;
    protected ?string $description = null;

    public function nullable(): static
    {
        $this->nullable = true;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
