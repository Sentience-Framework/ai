<?php

namespace Sentience\Ai\Apis\Anthropic;

use GuzzleHttp\Psr7\Response;
use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class AnthropicResponse extends ResponseAbstract
{
    protected array $response = [];

    public function __construct(Response $response, bool $hasStructuredOutput)
    {
        parent::__construct($hasStructuredOutput);

        $this->response = json_decode($response->getBody()->getContents(), true);
    }

    public function getContent(): string
    {
        foreach ($this->response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return $block['text'];
            }
        }

        return '';
    }

    public function getReasoningContent(): string
    {
        $reasoning = [];

        foreach ($this->response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'thinking') {
                $reasoning[] = $block['thinking'];
            }
        }

        return implode(PHP_EOL, $reasoning);
    }

    public function getToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    $block['id'],
                    $block['name'],
                    $block['input'] ?? []
                );
            }
        }

        return $toolCalls;
    }

    public function getFinishReason(): string
    {
        return $this->response['stop_reason'] ?? 'end_turn';
    }

    public function getStructuredOutput(): ?array
    {
        if (!$this->hasStructuredOutput) {
            return null;
        }

        $content = $this->getContent();

        $decoded = (bool) preg_match('/\`{3}json(.*)\`{3}?/m', $content, $match)
            ? json_decode(trim($match[1]), true)
            : null;

        if ($decoded) {
            return $decoded;
        }

        return (bool) preg_match('/(\{.*\})\S*$/m', $content, $match)
            ? json_decode(trim($match[1]), true)
            : json_decode(trim($content), true);
    }
}
