<?php

namespace Sentience\Ai\Messages;

class ToolMessage implements MessageInterface
{
    public Role $role = Role::Tool;

    public function __construct(
        public string $id,
        public string $content
    ) {
    }
}
