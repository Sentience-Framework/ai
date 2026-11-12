<?php

namespace Sentience\Ai\Apis\Anthropic;

class AnthropicCompatibleApi extends AnthropicApi
{
    protected function forcesLegacyStructuredOutput(): bool
    {
        return true;
    }
}
