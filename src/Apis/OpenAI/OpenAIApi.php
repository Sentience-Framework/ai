<?php

namespace Sentience\Ai\Apis\OpenAI;

use GuzzleHttp\Client;
use Sentience\Ai\Apis\ApiAbstract;
use Sentience\Ai\Apis\ToolCall;
use Sentience\Ai\Attachments\Base64Attachment;
use Sentience\Ai\Messages\AssistantMessage;
use Sentience\Ai\Messages\MessageInterface;
use Sentience\Ai\Messages\SystemMessage;
use Sentience\Ai\Messages\ToolMessage;
use Sentience\Ai\Messages\UserMessage;
use Sentience\Ai\Schema\Schemable;

class OpenAIApi extends ApiAbstract
{
    protected Client $client;

    public function __construct(string $baseUri, string $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => ['Authorization' => "Bearer $apiKey"]
        ]);
    }

    public function prompt(
        string $model,
        string $prompt,
        ?string $systemPrompt,
        array $tools,
        array $previousMessages,
        array $attachments,
        int $maxTokens,
        ?Schemable $structuredOutput
    ): OpenAIResponse {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = new SystemMessage($systemPrompt);
        }

        if ($structuredOutput) {
            $messages[] = $this->buildStructuredOutputMessage($structuredOutput);
        }

        array_push($messages, ...$previousMessages);

        $content = [$prompt];

        if ($attachments) {
            $content = [...$content, ...$attachments];
        }

        $messages[] = new UserMessage($content);

        $response = $this->client->post(
            '/v1/chat/completions',
            [
                'json' => [
                    'model' => $model,
                    'messages' => array_map(
                        fn (MessageInterface $message) => $this->buildMessage($message),
                        $messages
                    ),
                    'tools' => $this->buildToolsSchema($tools),
                    'max_tokens' => $maxTokens
                ]
            ]
        );

        return new OpenAIResponse($response, (bool) $structuredOutput);
    }

    protected function buildMessage(MessageInterface $message): array
    {
        return match (true) {
            $message instanceof SystemMessage => $this->buildSystemMessage($message),
            $message instanceof UserMessage => $this->buildUserMessage($message),
            $message instanceof AssistantMessage => $this->buildAssistentMessage($message),
            $message instanceof ToolMessage => $this->buildToolMessage($message)
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
            'role' => $message->role->value,
            'content' => $this->buildContent($message->content)
        ];
    }

    protected function buildAssistentMessage(AssistantMessage $message): array
    {
        return [
            'role' => $message->role->value,
            'content' => $message->content,
            'reasoning_content' => $message->reasoningContent,
            'tool_calls' => array_map(
                fn (ToolCall $toolCall): array => [
                    'id' => $toolCall->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $toolCall->name,
                        'arguments' => json_encode((object) $toolCall->arguments)
                    ]
                ],
                $message->toolCalls
            )
        ];
    }

    protected function buildToolMessage(ToolMessage $message): array
    {
        return [
            'role' => $message->role->value,
            'tool_call_id' => $message->id,
            'content' => $message->content
        ];
    }

    protected function buildBase64Content(Base64Attachment $attachment): array
    {
        if ($this->isImageFilename($attachment->filename ?? '')) {
            $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);

            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$this->mimeTypeForExtension($extension)};base64,{$attachment->base64}"
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
                'type' => 'function',
                'function' => [
                    'name' => $toolInterface->name(),
                    'description' => $toolInterface->description(),
                    'parameters' => $toolInterface->schema($name)
                ]
            ];
        }

        return $schema;
    }
}
