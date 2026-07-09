<?php

namespace Sentience\Ai\Apis\OpenAI;

use GuzzleHttp\Psr7\Response;
use Sentience\Ai\Apis\ResponseAbstract;
use Sentience\Ai\Apis\ToolCall;

class OpenAIResponse extends ResponseAbstract
{
    protected array $response = [];

    public function __construct(Response $response, bool $hasStructuredOutput)
    {
        parent::__construct($hasStructuredOutput);

        $this->response = json_decode($response->getBody()->getContents(), true);
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
