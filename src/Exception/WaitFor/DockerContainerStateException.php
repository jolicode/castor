<?php

namespace Castor\Exception\WaitFor;

class DockerContainerStateException extends \RuntimeException
{
    public function __construct(
        readonly string $containerName,
        readonly string $state,
    ) {
        parent::__construct("Container {$containerName} is in \"{$state}\" state. Please start it to use this feature.");
    }
}
