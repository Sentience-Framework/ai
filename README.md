# Sentience AI

The AI connector for the Sentience framework.

Sentience AI is a small, opinionated PHP library for talking to large language
models. It targets PHP 8.3 and wraps two underlying API shapes - the OpenAI
chat completions API and the Anthropic messages API - behind one consistent
interface. Anything that speaks OpenAI's protocol (OpenRouter, local inference
servers, compatible gateways) works through the OpenAI driver by pointing it at
a different base URI.

The goal is not to be a kitchen-sink SDK. It is to give a PHP application a
clean way to:

- send a prompt to a model,
- attach images and files,
- expose tools the model can call,
- ask for a structured JSON response,
- enable streaming over a PHP `Generator`, reading SSE chunks incrementally,
- and have tool calls loop to completion without writing that loop yourself.

All of that is one fluent call chain on a `Prompt` object, returning a
`Generator` that yields `ResponseInterface` values through each round of
the conversation.

## Installation

```bash
composer require sentience/ai
```

Requires PHP 8.3 or newer. The only runtime dependency is `guzzlehttp/guzzle`.

## Providers

Three providers ship out of the box, defined on the `Sentience\Ai\Api` enum:

| Provider    | Driver       | Notes                                                              |
|-------------|--------------|--------------------------------------------------------------------|
| `OpenAI`    | `OpenAIApi`  | The OpenAI chat completions API.                                   |
| `OpenRouter`| `OpenAIApi`  | OpenAI-compatible; point the base URI at `https://openrouter.ai`. |
| `Anthropic` | `AnthropicApi` | The Anthropic messages API.                                     |

Both drivers share `ApiAbstract`, so the message-building, attachment
formatting, and structured-output handling live in one place. The per-provider
classes only deal with the wire format differences (message roles, tool call
shapes, image content blocks, and SSE streaming events).

## Usage

### Connect

```php
use Sentience\Ai\Api;
use Sentience\Ai\Ai;

$ai = Ai::connect(
    Api::OpenAI,
    baseUri: 'https://api.openai.com/v1/',
    apiKey: getenv('OPENAI_API_KEY')
);
```

For OpenRouter, swap the enum and the base URI:

```php
$ai = Ai::connect(
    Api::OpenRouter,
    baseUri: 'https://openrouter.ai/api/v1/',
    apiKey: getenv('OPENROUTER_API_KEY')
);
```

For Anthropic:

```php
$ai = Ai::connect(
    Api::Anthropic,
    baseUri: 'https://api.anthropic.com/',
    apiKey: getenv('ANTHROPIC_API_KEY')
);
```

### A basic prompt

```php
foreach ($ai
    ->prompt('gpt-4o-mini', 'Summarise the plot of Moby-Dick in two sentences.')
    ->execute() as $response
) {
    echo $response->getContent();
}
```

Models can be passed as a plain string or as any backed enum; backed enums are
resolved to their `.value` automatically, which is handy when models live in
your own enum.

### System prompt and conversation history

```php
foreach ($ai
    ->prompt('claude-3-5-sonnet-latest', 'What did I just ask you about?')
    ->withSystemPrompt('You are a terse assistant. Answer in one sentence.')
    ->withPreviousMessages($priorTurns)
    ->execute() as $response
) {
    echo $response->getContent();
}
```

`withPreviousMessages` accepts an array of message objects (`UserMessage`,
`AssistantMessage`, `ToolMessage`). These are the same message types the
library produces internally, so you can round-trip a previous response through
`AssistantMessage::fromResponse($response)` and feed it back in.

### Attachments

```php
foreach ($ai
    ->prompt('gpt-4o', 'What is in this image?')
    ->withAttachment('/path/to/photo.png')
    ->withAttachment('/path/to/notes.txt')
    ->execute() as $response
) {
    echo $response->getContent();
}
```

Images (`png`, `jpg`, `jpeg`, `gif`, `webp`, `bmp`) are sent as image content
blocks. Anything else is decoded and embedded as a fenced text block so the
model sees the file contents directly. There are also `withBase64Attachment`
and `withRawAttachment` entry points for when the bytes are already in memory.

### Tools

Tools can be a closure, a callable, or any class implementing `ToolInterface`.
The simplest form is a closure:

```php
foreach ($ai
    ->prompt('gpt-4o', 'What is the weather in Amsterdam?')
    ->withTool(
        name: 'get_weather',
        tool: function (string $city): string {
            return "{$city}: 14C, light rain";
        },
        description: 'Get the current weather for a city.'
    )
    ->execute() as $response
) {
    echo $response->getContent();
}
```

If you do not supply an explicit schema, the library reflects on the closure's
parameters and builds one for you. Supported parameter types are `bool`, `int`,
`float`, `string`, and `array`. Nullable types are honoured. This is enough for
the vast majority of tools; for anything fancier, pass a `Schemable` schema
explicitly.

When `execute()` is called, the library will:

1. send the prompt,
2. collect any tool calls in the response,
3. run them against the registered tools,
4. append the assistant message and each tool result to the conversation,
5. and re-send, repeating until the model stops calling tools.

The tool-call loop runs automatically inside the generator. Each round of
the conversation yields a `ResponseInterface` that you can inspect or
consume before the loop continues to the next round.

For class-based tools, implement `ToolInterface` and register with
`withToolInterface`:

```php
final class SearchTool implements ToolInterface
{
    public function name(): string { return 'search'; }
    public function description(): string { return 'Search the knowledge base.'; }
    public function schema(): array { /* ... */ }
    public function execute(array $arguments): string { /* ... */ }
}

foreach ($ai
    ->prompt('claude-3-5-sonnet-latest', '...')
    ->withToolInterface(new SearchTool())
    ->execute() as $response
) {
    echo $response->getContent();
}
```

### Structured output

Ask for a JSON object back by passing an `ObjectType` schema:

```php
use Sentience\Ai\Schema\Schema;

$schema = Schema::object([
    'title'       => Schema::string(),
    'summary'     => Schema::string()->maxLength(280),
    'tags'        => Schema::array(Schema::string()),
    'sentiment'   => Schema::enum(['positive', 'neutral', 'negative']),
    'confidence'  => Schema::float()->nullable(),
]);

foreach ($ai
    ->prompt('gpt-4o', 'Analyse this article: ...')
    ->withStructuredOutput($schema)
    ->execute() as $response
) {
    $data = $response->getStructuredOutput();
}
```

The schema is injected as a system message instructing the model to return
minified JSON conforming to the schema. On the way back, `getStructuredOutput`
parses the response, handling both raw JSON and ```` ```json ```` fenced
blocks, and returns the decoded array (or `null` if the response was not
marked as structured).

### Streaming

```php
use Sentience\Ai\Apis\Length;

$generator = $ai
    ->prompt('gpt-4o-mini', 'Write a short essay on tide pools.')
    ->withStream()
    ->execute();

foreach ($generator as $response) {
    $response->read(Length::Small);

    echo $response->getContent();
}
```

Calling `withStream()` enables streaming mode on the HTTP request. The
`execute()` method now returns a `Generator` that yields `ResponseInterface`
objects. Each iteration yields the current response, and you call `read()` on
it to pull the next chunk of SSE data from the stream. The `read()` method
accepts a `Length` enum value (or an `int` byte count) to control chunk size:

| Case          | Bytes |
|---------------|-------|
| `ExtraSmall`  | 1024  |
| `Small`       | 4096  |
| `Medium`      | 8192  |
| `Large`       | 16384 |
| `ExtraLarge`  | 65536 |

The same loop handles content deltas, reasoning deltas (where the model emits
them), tool-call assembly, and the tool-call loop itself. When the model
requests tools, the generator automatically executes them, appends the results
to the conversation, and yields the next round — all within the same `foreach`.

For a non-streaming request, call `readAll()` on the yielded response to
consume the full body at once:

```php
$generator = $ai
    ->prompt('gpt-4o-mini', 'Write a short essay on tide pools.')
    ->execute();

foreach ($generator as $response) {
    $response->readAll();
}

echo $response->getContent();
```

## Schemas

The `Schema` facade produces JSON Schema fragments. Every type comes in
required and optional variants; the `required` flag on the factory selects
between them. Required properties end up in the resulting object's `required`
array automatically.

Available types:

- `Schema::bool()`
- `Schema::int()`
- `Schema::float()`
- `Schema::string()` - supports `minLength`, `maxLength`, `pattern`, `format`
- `Schema::enum(array $values)`
- `Schema::array(Schemable|array $items)`
- `Schema::object(array $properties)`

Every type is fluent: `->nullable()`, `->description(string)`, and the string
constraints chain. Anything implementing `Schemable` can be passed in places
that ask for a schema, which is how custom tool schemas and structured output
share the same machinery.

### Continuing the Conversation

If your application needs to maintain state across multiple user interactions (e.g., a chat interface), you can use `->continue()` on the generator. This method adds a new user message to the conversation history and re-sends the request, picking up exactly where the previous call left off.

```php
$generator = $ai
    ->prompt('gpt-4o', 'I have two dogs: Buster and Daisy.')
    ->execute();

foreach ($generator as $response) {
    echo $response->getContent();
}

$newGenerator = $ai->continue('Can you think of cool last names for these dogs', 'gpt-3.5');

foreach ($newGenerator as $response) {
    echo $response->getContent();
}
```

## Architecture

The public surface is small and the layering is intentional:

```
Ai                                  // entry point, picks a driver
  -> ApiInterface (OpenAI|Anthropic) // translates to the wire format
       -> Prompt                      // fluent builder
            -> ResponseGenerator       // owns the tool-call loop, yields responses
                 -> ResponseInterface  // content, reasoning, tool calls, finish reason
```

- `Sentience\Ai\Ai` - the facade. Connects, dispatches on the `Api` enum, and
  starts prompts.
- `Sentience\Ai\Apis\ApiAbstract` - shared behaviour: image detection, MIME
  handling, multipart content building, the structured-output system message,
  and the `/v1/models` listing.
- `Sentience\Ai\Apis\OpenAI\OpenAIApi` and `Sentience\Ai\Apis\Anthropic\AnthropicApi`
  - per-provider message and attachment formatting plus SSE stream parsing.
- `Sentience\Ai\Prompt` - the builder. Holds the prompt, system prompt,
  conversation history, attachments, tools, max tokens, structured output,
  and the stream flag. `execute()` returns a `Generator` via `ResponseGenerator`.
- `Sentience\Ai\Apis\ResponseGenerator` - an `IteratorAggregate` that wraps the
  tool-call loop. It yields `ResponseInterface` objects, re-sending with tool
  results until the model stops. In streaming mode, each yielded response
  can be consumed incrementally with `read()`.
- `Sentience\Ai\Apis\ResponseAbstract` - base class for provider responses.
  Handles both streaming and non-streaming HTTP bodies. Streaming reads SSE
  data via `read()` (with `Length` enum chunk sizes) and exposes `readAll()`
  for full consumption. Each provider subclass implements `handleSseData()`,
  `isStreamEnd()`, and `finalizeStream()`.
- `Sentience\Ai\Apis\Length` - an enum of chunk sizes (`ExtraSmall` through
  `ExtraLarge`) that controls how many bytes are read per `read()` call.
- `Sentience\Ai\Schema` - the schema factory and type hierarchy.
- `Sentience\Ai\Tools` - `Tool` (closure-backed, with reflection-driven schema
  generation) and `ToolInterface` for class-based tools.
- `Sentience\Ai\Messages` - `UserMessage`, `AssistantMessage`, `ToolMessage`,
  `SystemMessage`, and the `Role` enum.
- `Sentience\Ai\Attachments\Base64Attachment` - file and inline attachment
  helper.

Adding a provider means implementing `ApiInterface` (or extending
`ApiAbstract`) and adding a case to the `Api` enum plus a branch in `Ai`'s
constructor. Everything above the driver - prompt building, schemas, tools,
attachments, the loop - is shared.
