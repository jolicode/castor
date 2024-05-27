<?php

namespace Castor\Console;

use Castor\Console\Command\ComposerCommand;
use Castor\Container;
use Castor\Kernel;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal */
class Application extends SymfonyApplication
{
    public const NAME = 'castor';
    public const VERSION = 'v0.16.1';

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

        if ($this->shouldBootKernel($input)) {
            $this->kernel->boot($input, $output);
        }

        return parent::doRun($input, $output);
    }

    public function getHelp(): string
    {
        return $this->getLogo() . parent::getHelp();
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

    private function shouldBootKernel(InputInterface $input): bool
    {
        $name = $input->getFirstArgument();
        if (!$name) {
            return true;
        }

        try {
            $command = $this->find($name);
        } catch (CommandNotFoundException) {
            return true;
        }

        if ($command instanceof ComposerCommand) {
            return false;
        }

        return true;
    }

    private function getLogo(): string
    {
        if (!($_SERVER['CASTOR_TEST'] ?? false)) {
            $now = new \DateTime();
            $year = date('Y');

            // Halloween
            if ($now > new \DateTime($year . '-10-20') && $now < new \DateTime($year . '-11-02')) {
                return <<<'LOGO'

 ▄████▄   ▄▄▄        ██████ ▄▄▄█████▓ ▒█████   ██▀███
▒██▀ ▀█  ▒████▄    ▒██    ▒ ▓  ██▒ ▓▒▒██▒  ██▒▓██ ▒ ██▒
▒▓█    ▄ ▒██  ▀█▄  ░ ▓██▄   ▒ ▓██░ ▒░▒██░  ██▒▓██ ░▄█ ▒
▒▓▓▄ ▄██▒░██▄▄▄▄██   ▒   ██▒░ ▓██▓ ░ ▒██   ██░▒██▀▀█▄
▒ ▓███▀ ░ ▓█   ▓██▒▒██████▒▒  ▒██▒ ░ ░ ████▓▒░░██▓ ▒██▒
░ ░▒ ▒  ░ ▒▒   ▓▒█░▒ ▒▓▒ ▒ ░  ▒ ░░   ░ ▒░▒░▒░ ░ ▒▓ ░▒▓░
  ░  ▒     ▒   ▒▒ ░░ ░▒  ░ ░    ░      ░ ▒ ▒░   ░▒ ░ ▒░
░          ░   ▒   ░  ░  ░    ░      ░ ░ ░ ▒    ░░   ░
░ ░            ░  ░      ░               ░ ░     ░
░


LOGO;
            }

            // April fool
            if ('04-01' === $now->format('m-d')) {
                return <<<'LOGO'

ooo        ooooo           oooo                   .o88o.  o8o  oooo
`88.       .888'           `888                   888 `"  `"'  `888
 888b     d'888   .oooo.    888  oooo   .ooooo.  o888oo  oooo   888   .ooooo.
 8 Y88. .P  888  `P  )88b   888 .8P'   d88' `88b  888    `888   888  d88' `88b
 8  `888'   888   .oP"888   888888.    888ooo888  888     888   888  888ooo888
 8    Y     888  d8(  888   888 `88b.  888    .o  888     888   888  888    .o
o8o        o888o `Y888""8o o888o o888o `Y8bod8P' o888o   o888o o888o `Y8bod8P'


LOGO;
            }
        }

        return <<<'LOGO'

   █████████                     █████
  ███░░░░░███                   ░░███
 ███     ░░░   ██████    █████  ███████    ██████  ████████
░███          ░░░░░███  ███░░  ░░░███░    ███░░███░░███░░███
░███           ███████ ░░█████   ░███    ░███ ░███ ░███ ░░░
░░███     ███ ███░░███  ░░░░███  ░███ ███░███ ░███ ░███
 ░░█████████ ░░████████ ██████   ░░█████ ░░██████  █████
  ░░░░░░░░░   ░░░░░░░░ ░░░░░░     ░░░░░   ░░░░░░  ░░░░░


LOGO;
    }
}
