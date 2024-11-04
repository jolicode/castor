<?php

namespace Test\Attribute;

use Castor\Attribute\AsCommandArgument;
use Symfony\Component\Console\Completion\CompletionInput;

class AsArgument extends AsCommandArgument
{
    /**
     * @param array<string>|callable(CompletionInput): array<string> $autocomplete
     */
    public function test($autocomplete): void
    {
    }
}
