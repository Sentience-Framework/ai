<?php

namespace Sentience\Ai\Messages;

class SystemMessage implements MessageInterface
{
    public Role $role = Role::System;

    public function __construct(
        public string $content
    ) {
    }
}
