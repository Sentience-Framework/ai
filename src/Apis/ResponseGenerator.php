<?php

namespace Sentience\Ai\Apis;

use Generator;
use IteratorAggregate;
use Sentience\Ai\Exceptions\ToolNotFoundException;
use Sentience\Ai\Messages\AssistantMessage;
use Sentience\Ai\Messages\ToolMessage;
use Sentience\Ai\Schema\Schemable;

class ResponseGenerator implements IteratorAggregate
{
    public function __construct(
        protected ApiInterface $api,
        protected string $model,
        protected string $prompt,
        protected ?string $systemPrompt,
        protected array $attachments,
        protected array $tools,
        protected array $previousMessages,
        protected int $maxTokens,
        protected ?Schemable $structuredOutput,
        protected bool $stream
    ) {
    }

    public function getIterator(): Generator
    {
        $attachments = $this->attachments;

        while (true) {
            $response = $this->api->prompt(
                $this->model,
                $this->prompt,
                $this->systemPrompt,
                $attachments,
                $this->tools,
                $this->previousMessages,
                $this->maxTokens,
                $this->structuredOutput,
                $this->stream
            );

            if (!$this->stream) {
                $response->readStream(true);
            }

            yield $response;

            $toolCalls = $response->getToolCalls();

            if (count($toolCalls) == 0 && $response->getFinishReason() !== null) {
                break;
            }

            $this->previousMessages[] = new AssistantMessage(
                $response->getContent(),
                $response->getReasoningContent(),
                $toolCalls
            );

            foreach ($toolCalls as $toolCall) {
                if (!array_key_exists($toolCall->name, $this->tools)) {
                    throw new ToolNotFoundException("tool {$toolCall->name} does not exist");
                }

                $tool = $this->tools[$toolCall->name];
                $result = $tool->execute($toolCall->arguments);

                $this->previousMessages[] = new ToolMessage(
                    $toolCall->id,
                    $result
                );
            }
        }
    }
}
