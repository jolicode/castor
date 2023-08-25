<?php

namespace Castor\Console;

use Castor\Console\Command\InitCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
class CastorFileNotFoundApplication extends SymfonyApplication
{
    public function __construct(
        private readonly \RuntimeException $e,
    ) {
        parent::__construct(Application::NAME, Application::VERSION);
    }

    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        $this->add(new InitCommand($this->e));
        $this->setDefaultCommand('init');

        return parent::run($input, $output);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        if (!\in_array($input->getFirstArgument(), ['completion', 'list', 'help'], true)) {
            return 'init';
        }

        return $input->getFirstArgument();
    }
}
