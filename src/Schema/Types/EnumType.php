<?php

namespace Sentience\Ai\Schema\Types;

abstract class EnumType extends TypeAbstract
{
    public function __construct(
        protected array $values
    ) {
    }

    public function schema(): array
    {
        $type = (function (): string {
            foreach ($this->values as $value) {
                if (is_string($value)) {
                    continue;
                }

                return 'int';
            }

            return 'string';
        })();

        $schema = [
            'type' => $this->nullable ? [$type, 'null'] : $type,
            'enum' => $this->values
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }
}
