<?php

namespace Sentience\Ai\Schema\Types;

abstract class StringType extends TypeAbstract
{
    public const SCHEMA_TYPE = 'string';

    protected ?int $minLength = null;
    protected ?int $maxLength = null;
    protected ?string $pattern = null;
    protected ?string $format = null;

    public function minLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function schema(): array
    {
        $schema = ['type' => $this->nullable ? ['string', 'null'] : 'string'];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }

        return $schema;
    }
}
