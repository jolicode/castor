<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsSymfonyTask
{
    /**
     * @param string[] $console How to start the Symfony application
     */
    public function __construct(
        public ?string $name = null,
        public ?string $originalName = null,
        public readonly array $console = [\PHP_BINARY, 'bin/console'],
    ) {
    }
}
