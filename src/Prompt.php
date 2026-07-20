<?php

namespace Sentience\Ai;

use Closure;
use Sentience\Ai\Apis\ApiInterface;
use Sentience\Ai\Apis\ResponseGenerator;
use Sentience\Ai\Attachments\Base64Attachment;
use Sentience\Ai\Schema\Schemable;
use Sentience\Ai\Schema\Types\ObjectType;
use Sentience\Ai\Tools\Tool;
use Sentience\Ai\Tools\ToolInterface;

class Prompt
{
    public function __construct(
        protected ApiInterface $api,
        protected string $model,
        protected string $prompt,
        protected ?string $systemPrompt = null,
        protected array $previousMessages = [],
        protected array $attachments = [],
        protected array $tools = [],
        protected int $maxTokens = 1048578,
        protected int $maxReasoningTokens = 1048578,
        protected ?Schemable $structuredOutput = null,
        protected bool $stream = false
    ) {
    }

    public function withSystemPrompt(string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function withPreviousMessages(array $messages): static
    {
        $this->previousMessages = $messages;

        return $this;
    }

    public function withAttachment(string $filepath): static
    {
        $this->attachments[] = Base64Attachment::fromFilepath($filepath);

        return $this;
    }

    public function withBase64Attachment(string $base64, ?string $filename = null): static
    {
        $this->attachments[] = Base64Attachment::fromBase64($base64, $filename);

        return $this;
    }

    public function withRawAttachment(string $contents, ?string $filename = null): static
    {
        return $this->withBase64Attachment(
            base64_encode($contents),
            $filename
        );
    }

    public function withTool(
        string $name,
        string|array|callable $tool,
        ?Schemable $schema = null,
        ?string $description = null
    ): static {
        $this->tools[$name] = new Tool(
            $name,
            $description,
            Closure::fromCallable($tool),
            $schema
        );

        return $this;
    }

    public function withToolInterface(ToolInterface $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function withMaxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function withMaxReasoningTokens(int $maxReasoningTokens): static
    {
        $this->maxReasoningTokens = $maxReasoningTokens;

        return $this;
    }

    public function withStructuredOutput(ObjectType $schema): static
    {
        $this->structuredOutput = $schema;

        return $this;
    }

    public function withStream(): static
    {
        $this->stream = true;

        return $this;
    }

    public function execute(): ResponseGenerator
    {
        return new ResponseGenerator(
            $this->api,
            $this->model,
            $this->prompt,
            $this->systemPrompt,
            $this->attachments,
            $this->tools,
            $this->previousMessages,
            $this->maxTokens,
            $this->maxReasoningTokens,
            $this->structuredOutput,
            $this->stream
        );
    }
}
