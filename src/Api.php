<?php

namespace Sentience\Ai;

enum Api: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case OpenRouter = 'openrouter';
}
