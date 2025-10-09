<?php

namespace Castor\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsArgument extends AsCommandArgument
{
    /**
     * @param array<string>|callable(CompletionInput): array<string>|null $autocomplete
     */
    public function __construct(
        ?string $name = null,
        public readonly string $description = '',
        public readonly mixed $autocomplete = null,
    ) {
        parent::__construct($name);
    }
}
