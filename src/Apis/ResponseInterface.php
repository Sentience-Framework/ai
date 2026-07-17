<?php

namespace Sentience\Ai\Apis;

interface ResponseInterface
{
    public function readStream(bool $untilEof = false, ChunkSize $chunkSize = ChunkSize::M): bool;

    public function getContent(): string;
    public function getReasoningContent(): string;
    public function getToolCalls(): array;
    public function getFinishReason(): string;
    public function getStructuredOutput(): ?array;
}
