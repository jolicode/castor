<?php

namespace Castor\Console;

use Castor\Console\Command\InitCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class CastorFileNotFoundApplication extends SymfonyApplication
{
    public function __construct(
        private readonly \RuntimeException $e,
    ) {
        parent::__construct(Application::NAME, Application::VERSION);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->add(new InitCommand($this->e));
        $this->setDefaultCommand('init');

        return parent::run($input, $output);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        $commands = $this->all();

        if ($commands[$firstArgument = $input->getFirstArgument()] ?? false) {
            return $firstArgument;
        }

        return 'init';
    }
}
