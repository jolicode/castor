<?php

namespace Castor\Import\Remote;

use Composer\Command\SelfUpdateCommand;
use Composer\Console\Application as BaseApplication;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
final class ComposerApplication extends BaseApplication
{
    protected function getDefaultCommands(): array
    {
        $commands = [];

        foreach (parent::getDefaultCommands() as $command) {
            if ($command instanceof SelfUpdateCommand) {
                continue;
            }

            $commands[] = $command;
        }

        return $commands;
    }
}
