<?php

namespace Sentience\Ai\Apis\OpenAI;

use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class OpenAIResponse extends ResponseAbstract
{
    public function getContent(): string
    {
        if (!empty($this->response)) {
            $content = [];

            foreach ($this->response['choices'] as $choice) {
                $content[] = $choice['message']['content'];
            }

            return implode(PHP_EOL, $content);
        }

        return $this->content;
    }

    public function getReasoningContent(): string
    {
        if (!empty($this->response)) {
            $reasoningContent = [];

            foreach ($this->response['choices'] as $choice) {
                $reasoningContent[] = $choice['message']['reasoning_content'];
            }

            return implode(PHP_EOL, $reasoningContent);
        }

        return $this->reasoningContent;
    }

    public function getToolCalls(): array
    {
        if (!empty($this->response)) {
            $toolCalls = [];

            foreach ($this->response['choices'][0]['message']['tool_calls'] ?? [] as $toolCall) {
                $id = $toolCall['id'];
                $name = $toolCall['function']['name'];
                $arguments = json_decode($toolCall['function']['arguments'], true);
                $toolCalls[] = new ToolCall($id, $name, $arguments);
            }

            return $toolCalls;
        }

        return $this->toolCalls;
    }

    public function getFinishReason(): string
    {
        if (!empty($this->response)) {
            $finishReasons = [];

            foreach ($this->response['choices'] as $choice) {
                $finishReasons[] = $choice['finish_reason'];
            }

            return implode(PHP_EOL, $finishReasons);
        }

        return $this->finishReason;
    }

    protected function handleSseData(?array $data, string $rawJson): void
    {
        if ($data === null) {
            return;
        }

        if (!isset($data['choices'][0]['delta'])) {
            return;
        }

        $delta = $data['choices'][0]['delta'];

        if (isset($delta['content']) && $delta['content'] !== '') {
            $this->content .= $delta['content'];
        }

        if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
            $this->reasoningContent .= $delta['reasoning_content'];
        }

        if (isset($delta['tool_calls'])) {
            foreach ($delta['tool_calls'] as $toolCallDelta) {
                $index = $toolCallDelta['index'];

                if (!isset($this->toolCallData[$index])) {
                    $this->toolCallData[$index] = [
                        'id' => $toolCallDelta['id'] ?? null,
                        'function' => ['name' => '', 'arguments' => '']
                    ];
                }

                if (isset($toolCallDelta['function']['name']) && $toolCallDelta['function']['name'] !== '') {
                    $this->toolCallData[$index]['function']['name'] .= $toolCallDelta['function']['name'];
                }

                if (isset($toolCallDelta['function']['arguments'])) {
                    $this->toolCallData[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                }
            }
        }

        if (isset($data['choices'][0]['finish_reason']) && $data['choices'][0]['finish_reason'] !== null) {
            $this->finishReason = $data['choices'][0]['finish_reason'];

            if (!empty($this->toolCallData)) {
                $this->toolCalls = array_map(
                    fn (array $toolCallData) => new ToolCall(
                        $toolCallData['id'] ?? '',
                        $toolCallData['function']['name'] ?? '',
                        is_array(json_decode($toolCallData['function']['arguments'], true))
                        ? json_decode($toolCallData['function']['arguments'], true)
                        : []
                    ),
                    $this->toolCallData
                );
            }
        }
    }

    protected function isStreamEnd(string $rawJson): bool
    {
        return $rawJson === '[DONE]';
    }

    protected function finalizeStream(): void
    {
        $this->response = [
            'choices' => [
                [
                    'message' => [
                        'content' => $this->content,
                        'reasoning_content' => $this->reasoningContent,
                        'tool_calls' => array_map(
                            fn (ToolCall $toolCall) => [
                                'id' => $toolCall->id,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolCall->name,
                                    'arguments' => json_encode((object) $toolCall->arguments)
                                ]
                            ],
                            $this->toolCalls
                        )
                    ],
                    'finish_reason' => $this->finishReason
                ]
            ]
        ];
    }
}
