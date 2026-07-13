<?php

namespace Sentience\Ai\Apis\Anthropic;

use GuzzleHttp\Client;
use Sentience\Ai\Apis\ApiAbstract;
use Sentience\Ai\Apis\ResponseInterface;
use Sentience\Ai\Attachments\Base64Attachment;
use Sentience\Ai\Messages\AssistantMessage;
use Sentience\Ai\Messages\MessageInterface;
use Sentience\Ai\Messages\SystemMessage;
use Sentience\Ai\Messages\ToolMessage;
use Sentience\Ai\Messages\UserMessage;
use Sentience\Ai\Schema\Schemable;

class AnthropicApi extends ApiAbstract
{
    public function __construct(string $baseUri, string $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01'
            ]
        ]);
    }

    public function prompt(
        string $model,
        string $prompt,
        ?string $systemPrompt,
        array $attachments,
        array $tools,
        array $previousMessages,
        int $maxTokens,
        ?Schemable $structuredOutput,
        ?callable $onStreamEvent = null
    ): ResponseInterface {
        $messages = [];

        if ($structuredOutput) {
            $messages[] = $this->buildStructuredOutputMessage($structuredOutput);
        }

        array_push($messages, ...$previousMessages);

        $messages[] = new UserMessage($prompt, $attachments);

        $response = $this->client->post(
            '/v1/messages',
            [
                'stream' => $onStreamEvent !== null,
                'json' => array_filter([
                    'model' => $model,
                    'system' => $systemPrompt ?? '',
                    'messages' => array_map(
                        fn(MessageInterface $message) => $this->buildMessage($message),
                        $messages
                    ),
                    'tools' => $this->buildToolsSchema($tools),
                    'max_tokens' => $maxTokens,
                    'stream' => $onStreamEvent !== null
                ])
            ]
        );

        if ($onStreamEvent === null) {
            return new AnthropicResponse($response, (bool) $structuredOutput);
        }

        return (new AnthropicResponse(null, (bool) $structuredOutput))->stream($response, $onStreamEvent);
    }

    protected function buildMessage(MessageInterface $message): array
    {
        return match (true) {
            $message instanceof SystemMessage => $this->buildSystemMessage($message),
            $message instanceof UserMessage => $this->buildUserMessage($message),
            $message instanceof AssistantMessage => $this->buildAssistantMessage($message),
            $message instanceof ToolMessage => $this->buildToolResultMessage($message)
        };
    }

    protected function buildSystemMessage(SystemMessage $message): array
    {
        return [
            'role' => $message->role->value,
            'content' => $message->content
        ];
    }

    protected function buildUserMessage(UserMessage $message): array
    {
        return [
            'role' => 'user',
            'content' => $this->buildContent($message->content, $message->attachments)
        ];
    }

    protected function buildAssistantMessage(AssistantMessage $message): array
    {
        $content = [];

        if (!empty($message->reasoningContent)) {
            $content[] = [
                'type' => 'thinking',
                'thinking' => $message->reasoningContent
            ];
        }

        if (!empty($message->content)) {
            $content[] = [
                'type' => 'text',
                'text' => $message->content
            ];
        }

        foreach ($message->toolCalls as $toolCall) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall->id,
                'name' => $toolCall->name,
                'input' => $toolCall->arguments
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $content
        ];
    }

    protected function buildToolResultMessage(ToolMessage $message): array
    {
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message->id,
                    'content' => $message->content
                ]
            ]
        ];
    }

    protected function buildBase64Content(Base64Attachment $attachment): array
    {
        if ($this->isImageFilename($attachment->filename ?? '')) {
            $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $this->mimeTypeForExtension($extension),
                    'data' => $attachment->base64
                ]
            ];
        }

        $content = '<attachment>'
            . PHP_EOL . 'file contents: ' . ($attachment->filename ?? '')
            . PHP_EOL . '```'
            . PHP_EOL . base64_decode($attachment->base64)
            . PHP_EOL . '```'
            . '</attachment>';

        return [
            'type' => 'text',
            'text' => $content
        ];
    }

    protected function buildToolsSchema(array $tools): array
    {
        $schema = [];

        foreach ($tools as $name => $toolInterface) {
            $schema[] = [
                'name' => $toolInterface->name(),
                'description' => $toolInterface->description(),
                'input_schema' => $toolInterface->schema($name)
            ];
        }

        return $schema;
    }
}
