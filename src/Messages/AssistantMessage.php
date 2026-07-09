<?php

namespace Sentience\Ai\Messages;

use Sentience\Ai\Apis\ResponseInterface;

class AssistantMessage implements MessageInterface
{
    public Role $role = Role::Assistant;

    public static function fromResponse(ResponseInterface $response): static
    {
        return new static(
            $response->getContent(),
            $response->getReasoningContent(),
            $response->getToolCalls()
        );
    }

    public function __construct(
        public string $content,
        public string $reasoningContent,
        public array $toolCalls
    ) {
    }
}
