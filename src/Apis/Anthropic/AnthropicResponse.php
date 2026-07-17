<?php

namespace Sentience\Ai\Apis\Anthropic;

use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class AnthropicResponse extends ResponseAbstract
{
    public function getContent(): string
    {
        if (!empty($this->response)) {
            foreach ($this->response['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    return $block['text'];
                }
            }

            return '';
        }

        return $this->content;
    }

    public function getReasoningContent(): string
    {
        if (!empty($this->response)) {
            $reasoning = [];

            foreach ($this->response['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'thinking') {
                    $reasoning[] = $block['thinking'];
                }
            }

            return implode(PHP_EOL, $reasoning);
        }

        return $this->reasoningContent;
    }

    public function getToolCalls(): array
    {
        if (!empty($this->response)) {
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

        return $this->toolCalls;
    }

    public function getFinishReason(): string
    {
        if (!empty($this->response)) {
            return $this->response['stop_reason'] ?? 'end_turn';
        }

        return $this->finishReason ?: 'end_turn';
    }

    protected function handleSseData(?array $data, string $rawJson): void
    {
        if ($data === null) {
            return;
        }

        $type = $data['type'] ?? '';

        match ($type) {
            'content_block_start' => $this->handleContentBlockStart($data),
            'content_block_delta' => $this->handleContentBlockDelta($data),
            'message_delta' => $this->handleMessageDelta($data),
            default => null
        };
    }

    protected function handleContentBlockStart(array $data): void
    {
        $block = $data['content_block'] ?? [];

        if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
            $this->content .= $block['text'];
        } elseif (($block['type'] ?? '') === 'thinking' && isset($block['thinking'])) {
            $this->reasoningContent .= $block['thinking'];
        } elseif (($block['type'] ?? '') === 'tool_use') {
            $index = $data['index'] ?? count($this->toolCallData);
            $this->toolCallData[$index] = [
                'id' => $block['id'] ?? '',
                'name' => $block['name'] ?? '',
                'input_json' => ''
            ];
        }
    }

    protected function handleContentBlockDelta(array $data): void
    {
        $delta = $data['delta'] ?? [];

        if (($delta['type'] ?? '') === 'text_delta' && isset($delta['text'])) {
            $this->content .= $delta['text'];
        } elseif (($delta['type'] ?? '') === 'thinking_delta' && isset($delta['thinking'])) {
            $this->reasoningContent .= $delta['thinking'];
        } elseif (($delta['type'] ?? '') === 'input_json_delta' && isset($delta['partial_json'])) {
            $index = $data['index'] ?? 0;
            $this->toolCallData[$index]['input_json'] .= $delta['partial_json'];
        }
    }

    protected function handleMessageDelta(array $data): void
    {
        $delta = $data['delta'] ?? [];

        if (isset($delta['stop_reason'])) {
            $this->finishReason = $delta['stop_reason'];

            if (!empty($this->toolCallData)) {
                $this->toolCalls = array_map(
                    fn (array $toolCallData) => new ToolCall(
                        $toolCallData['id'] ?? '',
                        $toolCallData['name'] ?? '',
                        is_array(json_decode($toolCallData['input_json'], true))
                        ? json_decode($toolCallData['input_json'], true)
                        : []
                    ),
                    $this->toolCallData
                );
            }
        }
    }

    protected function isStreamEnd(string $rawJson): bool
    {
        return false;
    }

    protected function finalizeStream(): void
    {
        $contentBlocks = [];

        if ($this->reasoningContent !== '') {
            $contentBlocks[] = ['type' => 'thinking', 'thinking' => $this->reasoningContent];
        }

        if ($this->content !== '') {
            $contentBlocks[] = ['type' => 'text', 'text' => $this->content];
        }

        foreach ($this->toolCalls as $toolCall) {
            $contentBlocks[] = [
                'type' => 'tool_use',
                'id' => $toolCall->id,
                'name' => $toolCall->name,
                'input' => $toolCall->arguments
            ];
        }

        $responseData = [
            'content' => $contentBlocks
        ];

        if ($this->finishReason !== '') {
            $responseData['stop_reason'] = $this->finishReason;
        }

        $this->response = $responseData;
    }

    protected function parseStructuredOutput(string $content): ?array
    {
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
