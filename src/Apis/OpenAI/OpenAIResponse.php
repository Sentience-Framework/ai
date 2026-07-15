<?php

namespace Sentience\Ai\Apis\OpenAI;

use GuzzleHttp\Psr7\Response;
use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class OpenAIResponse extends ResponseAbstract
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
            $this->response = $response !== null
                ? json_decode($response->getBody()->getContents(), true)
                : [];

            return;
        }

        $content = '';
        $reasoningContent = '';
        $toolCalls = [];
        $finishReason = '';
        $accumulatedToolCalls = [];

        $getStructuredOutput = $this->hasStructuredOutput
            ? fn (string $content): ?array => $this->parseStructuredOutput($content)
            : null;

        $buildResponse = function () use (&$content, &$reasoningContent, &$toolCalls, &$finishReason, $getStructuredOutput) {
            $syntheticArray = [
                'choices' => [
                    [
                        'message' => [
                            'content' => $content,
                            'reasoning_content' => $reasoningContent,
                            'tool_calls' => array_map(
                                fn (ToolCall $toolCall) => [
                                    'id' => $toolCall->id,
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $toolCall->name,
                                        'arguments' => json_encode((object) $toolCall->arguments)
                                    ]
                                ],
                                $toolCalls
                            )
                        ],
                        'finish_reason' => $finishReason
                    ]
                ]
            ];

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

                if ($line === '' || str_starts_with($line, 'event:')) {
                    continue;
                }

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $jsonString = substr($line, 6);

                if ($jsonString === '[DONE]') {
                    break 2;
                }

                $chunk = json_decode($jsonString, true);

                if (!isset($chunk['choices'][0]['delta'])) {
                    continue;
                }

                $delta = $chunk['choices'][0]['delta'];

                if (isset($delta['content']) && $delta['content'] !== '') {
                    $content .= $delta['content'];
                }

                if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                    $reasoningContent .= $delta['reasoning_content'];
                }

                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCallDelta) {
                        $index = $toolCallDelta['index'];

                        if (!isset($accumulatedToolCalls[$index])) {
                            $accumulatedToolCalls[$index] = [
                                'id' => $toolCallDelta['id'] ?? null,
                                'function' => ['name' => '', 'arguments' => '']
                            ];
                        }

                        if (isset($toolCallDelta['function']['name']) && $toolCallDelta['function']['name'] !== '') {
                            $accumulatedToolCalls[$index]['function']['name'] .= $toolCallDelta['function']['name'];
                        }

                        if (isset($toolCallDelta['function']['arguments'])) {
                            $accumulatedToolCalls[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                        }
                    }
                }

                if (isset($chunk['choices'][0]['finish_reason']) && $chunk['choices'][0]['finish_reason'] !== null) {
                    $finishReason = $chunk['choices'][0]['finish_reason'];

                    if (!empty($accumulatedToolCalls)) {
                        $toolCalls = array_map(
                            fn (array $toolCallData) => new ToolCall(
                                $toolCallData['id'] ?? '',
                                $toolCallData['function']['name'] ?? '',
                                is_array(json_decode($toolCallData['function']['arguments'], true))
                                ? json_decode($toolCallData['function']['arguments'], true)
                                : []
                            ),
                            $accumulatedToolCalls
                        );
                    }
                }

                $onStreamEvent($buildResponse());
            }
        }

        $this->response = [
            'choices' => [
                [
                    'message' => [
                        'content' => $content,
                        'reasoning_content' => $reasoningContent,
                        'tool_calls' => array_map(
                            fn (ToolCall $toolCall) => [
                                'id' => $toolCall->id,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolCall->name,
                                    'arguments' => json_encode((object) $toolCall->arguments)
                                ]
                            ],
                            $toolCalls
                        )
                    ],
                    'finish_reason' => $finishReason
                ]
            ]
        ];
    }

    public function getContent(): string
    {
        $content = [];

        foreach ($this->response['choices'] as $choice) {
            $content[] = $choice['message']['content'];
        }

        return implode(PHP_EOL, $content);
    }

    public function getReasoningContent(): string
    {
        $reasoningContent = [];

        foreach ($this->response['choices'] as $choice) {
            $reasoningContent[] = $choice['message']['reasoning_content'];
        }

        return implode(PHP_EOL, $reasoningContent);
    }

    public function getToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->response['choices'][0]['message']['tool_calls'] ?? [] as $toolCall) {
            $id = $toolCall['id'];
            $name = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);

            $toolCalls[] = new ToolCall($id, $name, $arguments);
        }

        return $toolCalls;
    }

    public function getFinishReason(): string
    {
        $finishReasons = [];

        foreach ($this->response['choices'] as $choice) {
            $finishReasons[] = $choice['finish_reason'];
        }

        return implode(PHP_EOL, $finishReasons);
    }
}
