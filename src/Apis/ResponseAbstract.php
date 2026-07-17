<?php

namespace Sentience\Ai\Apis;

use BackedEnum;
use GuzzleHttp\Psr7\Response;

abstract class ResponseAbstract implements ResponseInterface
{
    protected array $response = [];
    protected string $content = '';
    protected string $reasoningContent = '';
    protected array $toolCalls = [];
    protected string $finishReason = '';
    protected array $toolCallData = [];
    protected string $buffer = '';
    protected bool $streamExhausted = false;

    public function __construct(
        protected Response $httpResponse,
        protected bool $stream,
        protected bool $hasStructuredOutput
    ) {
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

    public function read(int|Length $length = Length::Medium): bool
    {
        if ($this->streamExhausted || $this->httpResponse === null) {
            return false;
        }

        if (!$this->stream) {
            $this->response = json_decode($this->httpResponse->getBody()->getContents(), true) ?: [];
            $this->streamExhausted = true;

            return true;
        }

        $body = $this->httpResponse->getBody();

        if ($body->eof()) {
            $this->finalizeStream();
            $this->streamExhausted = true;

            return true;
        }

        $this->buffer .= $body->read($length instanceof BackedEnum ? $length->value : $length);
        $lines = explode("\n", $this->buffer);
        $this->buffer = array_pop($lines);

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if ($line === '' || str_starts_with($line, 'event:')) {
                continue;
            }

            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $rawJson = trim(substr($line, 6));

            if ($this->isStreamEnd($rawJson)) {
                $this->finalizeStream();
                $this->streamExhausted = true;

                return true;
            }

            $data = json_decode($rawJson, true);

            if (!is_array($data)) {
                continue;
            }

            $this->handleSseData($data, $rawJson);
        }

        return true;
    }

    public function readAll(int|Length $chunckSize = Length::Medium): void
    {
        if ($this->streamExhausted || $this->httpResponse === null) {
            return;
        }

        while (true) {
            if (!$this->read($chunckSize)) {
                return;
            }
        }
    }

    abstract protected function handleSseData(?array $data, string $rawJson): void;

    abstract protected function isStreamEnd(string $rawJson): bool;

    abstract protected function finalizeStream(): void;
}
