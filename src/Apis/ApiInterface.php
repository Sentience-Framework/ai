<?php

namespace Sentience\Ai\Apis;

use Sentience\Ai\Schema\Schemable;

interface ApiInterface
{
    public function prompt(
        string $model,
        array $messages,
        ?string $systemPrompt,
        array $tools,
        int $maxTokens,
        int $maxReasoningTokens,
        ?Schemable $structuredOutput,
        bool $stream
    ): ResponseInterface;

    public function models(): array;
}
