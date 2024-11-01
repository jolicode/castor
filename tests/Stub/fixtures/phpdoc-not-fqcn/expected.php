<?php

namespace Test\Attribute;

class AsArgument extends \Castor\Attribute\AsCommandArgument
{
    /**
     * @param array<string>|callable(\Symfony\Component\Console\Completion\CompletionInput): array<string> $autocomplete
     */
    public function test($autocomplete): void
    {
    }
}
