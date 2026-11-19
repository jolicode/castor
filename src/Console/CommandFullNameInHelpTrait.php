<?php

namespace Castor\Console;

use Symfony\Component\Console\Command\Command;

/** @internal */
trait CommandFullNameInHelpTrait
{
    /**
     * Since Symfony Console 8.2, the help of the default commands starts their
     * examples with the PHP binary ("/usr/bin/php bin/castor list"), while
     * Castor runs on its own. It would also quote "castor composer" as a single
     * script, see Composer::run().
     *
     * @param Command[] $commands
     *
     * @return Command[]
     */
    private function useCommandFullNameInHelp(array $commands): array
    {
        foreach ($commands as $command) {
            $command->setHelp(str_replace('%command.invocation%', '%command.full_name%', $command->getHelp()));
        }

        return $commands;
    }
}
