<?php

namespace Sentience\Ai\Messages;

class UserMessage implements MessageInterface
{
    public Role $role = Role::User;
    public array $content;

    public function __construct(
        string|array $content,
        public array $attachments
    ) {
        $this->content = (array) $content;
    }
}
