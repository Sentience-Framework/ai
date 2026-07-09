<?php

namespace Sentience\Ai\Tools;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function schema(): array;
    public function execute(array $arguments): string;
}
