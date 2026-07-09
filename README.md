```
DISCLAIMER:

Yes, this README is AI generated. This is a placeholder until i make a proper README
```

# 🧠 Sentience AI Connector

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)

**The robust, developer-friendly interface for orchestrating Large Language Models (LLMs) in PHP.**

`Sentience AI Connector` abstracts the complexity of interacting with various AI providers, allowing you to focus on building intelligent applications. Whether you are working with OpenAI, Anthropic, or local LLM runners, Sentience provides a unified, type-safe way to manage prompts, file attachments, and structured data.

---

## ✨ Key Features

| Feature | Description |
| :--- | :--- |
| 🚀 **Multi-Provider Support** | Seamlessly switch between `OpenAI`, `Anthropic`, or any OpenAI-compatible local API (like LM Studio or Ollama). |
| 🧠 **Advanced Prompting** | Chain complex instructions using System Prompts, user text, and direct local file attachments. |
| 🏗️ **Structured Output** | Stop parsing messy strings. Use a fluent Schema builder to get predictable, typed JSON responses. |
| 📂 **File Context** | Easily inject local files into the model's context window with a single method call. |

---

## 🚀 Quick Start

### Installation

Install via Composer:

```bash
composer require sentience/ai-connector
```

### Usage Example

The following example demonstrates how to connect to a local LLM server, attach a configuration file, and request a highly structured summary.

```php
use Sentience\Ai\Ai;
use Sentience\Ai\Api;
use Sentience\Ai\Schema;

// 1. Connect to a provider (OpenAI, Anthropic, or Local)
$ai = Ai::connect(
    Api::OpenAI, 
    'http://localhost:1234', // Base URL (e.g., LM Studio)
    'your-api-key'          // API Key
);

// 2. Initialize a prompt
$prompt = $ai->prompt('Review the provided files and summarize their contents.');

// 3. Configure the conversation context
$prompt->withSystemPrompt('You are a helpful assistant. Answer in maximum 2 sentences.')
       
$prompt->->withAttachment(SENTIENCE_DIR . '/docker-compose.yml');

// 4. Define the expected structure (Schema-driven development)
$prompt->withStructuredOutput(
    Schema::object([
        'files' => Schema::array(
            Schema::object([
                'filetype'         => Schema::string(),
                'contents_summary' => Schema::string(),
            ])
        )
    ])
);

// 5. Execute and receive structured results
$response = $prompt->execute();
```

---

## 🛠 Supported Providers

Currently, `Sentience AI Connector` provides native support for:

*   **OpenAI** (GPT-4o, GPT-4-Turbo, etc.)
*   **Anthropic** (Claude 3.5 Sonnet, Opus, Haiku)
*   **OpenAI-Compatible APIs** (Local hosting via LM Studio, Ollama, LocalAI, etc.)

---

## 📦 Core Concepts

### The Prompt Object
The `Prompt` object is the heart of the library. It follows a fluent interface pattern, allowing you to build a "contextual payload" before execution. This prevents "prompt spaghetti" in your controllers.

### Schema-Driven Design
Instead of regex-parsing AI responses, use the `Schema` builder. It generates the necessary instructions for the LLM to return valid JSON, significantly increasing the reliability of your application logic.
