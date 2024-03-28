<?php

namespace Castor\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsOption extends AsCommandArgument
{
    /**
     * @param string|array<string>|null                      $shortcut
     * @param array<string>                                  $suggestedValues
     * @param mixed|callable(CompletionInput): array<string> $autocomplete
     */
    public function __construct(
        ?string $name = null,
        public readonly string|array|null $shortcut = null,
        public readonly ?int $mode = null,
        public readonly string $description = '',
        public readonly array $suggestedValues = [],
        public readonly mixed $autocomplete = null,
    ) {
        parent::__construct($name);
    }
}
