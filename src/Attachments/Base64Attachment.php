<?php

namespace Sentience\Ai\Attachments;

use InvalidArgumentException;
use RuntimeException;

class Base64Attachment
{
    public function __construct(
        public string $base64,
        public ?string $filename = null
    ) {
    }

    public static function fromFilepath(string $filepath): self
    {
        if (!file_exists($filepath)) {
            throw new InvalidArgumentException("File not found: {$filepath}");
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read file: {$filepath}");
        }

        return new self(
            base64_encode($contents),
            basename($filepath)
        );
    }

    public static function fromBase64(string $base64, ?string $filename = null): self
    {
        return new self($base64, $filename);
    }
}
