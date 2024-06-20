<?php

namespace Castor\CommandBuilder;

trait WithWorkingDirectoryTrait
{
    private ?string $workingDirectory = null;

    public function withWorkingDirectory(string $workingDirectory): self
    {
        /** @var self $this */
        $new = clone $this;
        $new->workingDirectory = $workingDirectory;

        return $new;
    }
}
