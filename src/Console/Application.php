<?php

namespace Castor\Console;

use Castor\Container;
use Castor\Kernel;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.15.0';

    private Command $command;

    public function __construct(
        private readonly ContainerBuilder $containerBuilder,
        private readonly Kernel $kernel,
    ) {
        parent::__construct(static::NAME, static::VERSION);
    }

    /**
     * @return ($allowNull is true ? ?Command : Command)
     */
    public function getCommand(bool $allowNull = false): ?Command
    {
        return $this->command ?? ($allowNull ? null : throw new \LogicException('Command not available yet.'));
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->containerBuilder->set(InputInterface::class, $input);
        $this->containerBuilder->set(OutputInterface::class, $output);

        // @phpstan-ignore-next-line
        Container::set($this->containerBuilder->get(Container::class));

        $this->kernel->boot($input, $output);

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $this->command = $command;

        return parent::doRunCommand($command, $input, $output);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(
            new InputOption(
                'no-remote',
                null,
                InputOption::VALUE_NONE,
                'Skip the import of all remote remote packages',
            )
        );

        $definition->addOption(
            new InputOption(
                'update-remotes',
                null,
                InputOption::VALUE_NONE,
                'Force the update of remote packages',
            )
        );

        return $definition;
    }
}
