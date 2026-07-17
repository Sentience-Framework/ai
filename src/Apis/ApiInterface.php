<?php

namespace Sentience\Ai\Apis;

use Sentience\Ai\Schema\Schemable;

interface ApiInterface
{
    public function prompt(
        string $model,
        string $prompt,
        ?string $systemPrompt,
        array $attachments,
        array $tools,
        array $previousMessages,
        int $maxTokens,
        ?Schemable $structuredOutput,
        bool $stream = false
    ): ResponseInterface;

    public function models(): array;
}
