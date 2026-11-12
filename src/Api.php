<?php

namespace Sentience\Ai;

enum Api: string
{
    case OpenAI = 'openai';
    case OpenAICompatible = 'openai_compatible';
    case Anthropic = 'anthropic';
    case AnthropicCompatible = 'anthropic_compatible';
}
