<?php

namespace Sentience\Ai\Apis;

class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments
    ) {
    }
}
