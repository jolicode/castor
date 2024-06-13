<?php

namespace Castor\CommandBuilder;

use Castor\Context;

trait WithWorkingDirectoryTrait
{
    public function withWorkingDirectory(string $workingDirectory): self
    {
        /** @var self $this */
        $new = clone $this;
        $new->addContextModifier(fn (Context $context) => $context->withWorkingDirectory($workingDirectory));

        return $new;
    }
}
