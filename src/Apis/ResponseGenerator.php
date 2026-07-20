<?php

namespace Sentience\Ai\Apis;

use Generator;
use IteratorAggregate;
use Sentience\Ai\Exceptions\ExecutionNotStartedException;
use Sentience\Ai\Exceptions\ToolNotFoundException;
use Sentience\Ai\Messages\AssistantMessage;
use Sentience\Ai\Messages\ToolMessage;
use Sentience\Ai\Messages\UserMessage;
use Sentience\Ai\Prompt;
use Sentience\Ai\Schema\Schemable;

class ResponseGenerator implements IteratorAggregate
{
    protected array $messages = [];

    public function __construct(
        protected ApiInterface $api,
        protected string $model,
        protected string $prompt,
        protected ?string $systemPrompt,
        protected array $attachments,
        protected array $tools,
        protected array $previousMessages,
        protected int $maxTokens,
        protected int $maxReasoningTokens,
        protected ?Schemable $structuredOutput,
        protected bool $stream
    ) {
    }

    public function getIterator(): Generator
    {
        $this->messages = $this->previousMessages;

        $this->messages[] = new UserMessage($this->prompt, $this->attachments);

        while (true) {
            $response = $this->api->prompt(
                $this->model,
                $this->messages,
                $this->systemPrompt,
                $this->tools,
                $this->maxTokens,
                $this->maxReasoningTokens,
                $this->structuredOutput,
                $this->stream
            );

            if (!$this->stream) {
                $response->readAll();
            }

            yield $response;

            $toolCalls = $response->getToolCalls();

            $this->messages[] = new AssistantMessage(
                $response->getContent(),
                $response->getReasoningContent(),
                $toolCalls
            );

            if (count($toolCalls) == 0 && !empty($response->getFinishReason())) {
                break;
            }

            foreach ($toolCalls as $toolCall) {
                if (!array_key_exists($toolCall->name, $this->tools)) {
                    throw new ToolNotFoundException("tool {$toolCall->name} does not exist");
                }

                $tool = $this->tools[$toolCall->name];
                $result = $tool->execute($toolCall->arguments);

                $this->messages[] = new ToolMessage(
                    $toolCall->id,
                    $result
                );
            }
        }
    }

    public function getMessages(): array
    {
        if (count($this->messages) === 0) {
            throw new ExecutionNotStartedException('loop through the iterator before calling ->getMessages()');
        }

        return $this->messages;
    }

    public function continue(string $prompt, ?string $model = null): Prompt
    {
        return new Prompt(
            $this->api,
            $model ?? $this->model,
            $prompt,
            $this->systemPrompt,
            $this->messages,
            [],
            $this->tools,
            $this->maxTokens,
            $this->maxReasoningTokens,
            $this->structuredOutput,
            $this->stream
        );
    }
}
