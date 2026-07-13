<?php

namespace Sentience\Ai\Apis;

use Closure;

class StreamedResponse implements ResponseInterface
{
    public function __construct(
        private string $content,
        private string $reasoningContent,
        private array $toolCalls,
        private string $finishReason,
        private ?Closure $getStructuredOutput
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getReasoningContent(): string
    {
        return $this->reasoningContent;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    public function getStructuredOutput(): ?array
    {
        if (!$this->getStructuredOutput) {
            return null;
        }

        return ($this->getStructuredOutput)($this->content);
    }
}
