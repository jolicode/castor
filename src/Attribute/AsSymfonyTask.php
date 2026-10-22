<?php

namespace Castor\Attribute;

/**
 * Turns a command of a Symfony application into a task: put it on the class of
 * the command.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsSymfonyTask
{
    /**
     * @param ?string  $name         Defaults to the name of the command
     * @param ?string  $originalName The name of the command in the Symfony application. Defaults to the one of its AsCommand attribute
     * @param string[] $console      How to start the Symfony application
     * @param ?bool    $usePhpRunner Runs the console with the PHP of Castor. Defaults to true when the console is a PHP file
     */
    public function __construct(
        public ?string $name = null,
        public ?string $originalName = null,
        public readonly array $console = ['bin/console'],
        public ?bool $usePhpRunner = null,
    ) {
    }
}
