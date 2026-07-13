<?php

namespace Sentience\Ai\Apis;

abstract class ResponseAbstract implements ResponseInterface
{
    public function __construct(protected bool $hasStructuredOutput)
    {
    }

    public function getStructuredOutput(): ?array
    {
        if (!$this->hasStructuredOutput) {
            return null;
        }

        return $this->parseStructuredOutput($this->getContent());
    }

    protected function parseStructuredOutput(string $content): ?array
    {
        return (bool) preg_match('/\`{3}json(.*)\`{3}?/m', $content, $match)
            ? json_decode(trim($match[1]), true)
            : json_decode(trim($content), true);
    }
}
