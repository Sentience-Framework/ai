<?php

namespace Sentience\Ai\Apis\Anthropic;

use GuzzleHttp\Psr7\Response;
use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class AnthropicResponse extends ResponseAbstract
{
    protected array $response = [];

    public function __construct(null|array|Response $response, bool $hasStructuredOutput = false, ?callable $onStreamEvent = null)
    {
        parent::__construct($hasStructuredOutput);

        if (is_array($response)) {
            $this->response = $response;
            return;
        }

        if ($onStreamEvent === null) {
            $responseData = $response !== null
                ? json_decode($response->getBody()->getContents(), true)
                : [];

            return;
        }

        $content = '';
        $reasoningContent = '';
        $toolCalls = [];
        $finishReason = '';
        $accumulatedToolCalls = [];

        $buildResponse = function () use (&$content, &$reasoningContent, &$toolCalls, &$finishReason) {
            // Build the exact same synthetic array structure used at the end of streaming.
            $contentBlocks = [];

            if ($reasoningContent !== '') {
                $contentBlocks[] = ['type' => 'thinking', 'thinking' => $reasoningContent];
            }

            if ($content !== '') {
                $contentBlocks[] = ['type' => 'text', 'text' => $content];
            }

            foreach ($toolCalls as $toolCall) {
                $contentBlocks[] = [
                    'type' => 'tool_use',
                    'id' => $toolCall->id,
                    'name' => $toolCall->name,
                    'input' => $toolCall->arguments
                ];
            }

            $syntheticArray = ['content' => $contentBlocks];

            if ($finishReason !== '') {
                $syntheticArray['stop_reason'] = $finishReason;
            }

            return new self($syntheticArray, $this->hasStructuredOutput);
        };

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(8192);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = rtrim($line, "\r");

                if ($line === '' || str_starts_with($line, 'event: ')) {
                    continue;
                }

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = json_decode(substr($line, 6), true);

                if (!is_array($data)) {
                    continue;
                }

                $type = $data['type'] ?? '';
                $updated = false;

                if ($type === 'content_block_start') {
                    $block = $data['content_block'] ?? [];

                    if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                        $content .= $block['text'];
                        $updated = true;
                    } elseif (($block['type'] ?? '') === 'thinking' && isset($block['thinking'])) {
                        $reasoningContent .= $block['thinking'];
                        $updated = true;
                    } elseif (($block['type'] ?? '') === 'tool_use') {
                        $index = $data['index'] ?? count($accumulatedToolCalls);
                        $accumulatedToolCalls[$index] = [
                            'id' => $block['id'] ?? '',
                            'name' => $block['name'] ?? '',
                            'input_json' => ''
                        ];
                    }
                } elseif ($type === 'content_block_delta') {
                    $delta = $data['delta'] ?? [];

                    if (($delta['type'] ?? '') === 'text_delta' && isset($delta['text'])) {
                        $content .= $delta['text'];
                        $updated = true;
                    } elseif (($delta['type'] ?? '') === 'thinking_delta' && isset($delta['thinking'])) {
                        $reasoningContent .= $delta['thinking'];
                        $updated = true;
                    } elseif (($delta['type'] ?? '') === 'input_json_delta' && isset($delta['partial_json'])) {
                        $index = $data['index'] ?? 0;
                        $accumulatedToolCalls[$index]['input_json'] .= $delta['partial_json'];
                    }
                } elseif ($type === 'message_delta') {
                    $delta = $data['delta'] ?? [];

                    if (isset($delta['stop_reason'])) {
                        $finishReason = $delta['stop_reason'];

                        if (!empty($accumulatedToolCalls)) {
                            $toolCalls = array_map(
                                fn (array $toolCallData) => new ToolCall(
                                    $toolCallData['id'] ?? '',
                                    $toolCallData['name'] ?? '',
                                    is_array(json_decode($toolCallData['input_json'], true))
                                    ? json_decode($toolCallData['input_json'], true)
                                    : []
                                ),
                                $accumulatedToolCalls
                            );
                        }

                        $updated = true;
                    }
                } elseif ($type === 'ping') {
                    continue;
                }

                if ($updated) {
                    $onStreamEvent($buildResponse());
                }
            }
        }

        $contentBlocks = [];

        if ($reasoningContent !== '') {
            $contentBlocks[] = ['type' => 'thinking', 'thinking' => $reasoningContent];
        }

        if ($content !== '') {
            $contentBlocks[] = ['type' => 'text', 'text' => $content];
        }

        foreach ($toolCalls as $toolCall) {
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

        if ($finishReason !== '') {
            $responseData['stop_reason'] = $finishReason;
        }

        $this->response = $responseData;
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
